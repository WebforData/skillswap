<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LitigeCreationRequest;
use App\Http\Requests\ReservationAnnulationRequest;
use App\Http\Requests\ReservationDemandeRequest;
use App\Http\Requests\ReservationRefusRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * Liste paginée des réservations de l'utilisateur connecté (RG19).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $this->reservationService->getMesReservations($user, $request->all(), $perPage);

        $data = $paginator->getCollection()->map(function (Reservation $reservation) use ($user) {
            return $this->formatReservationResponse($reservation, $user);
        });

        return response()->json([
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'reservations' => $data,
        ], Response::HTTP_OK);
    }

    /**
     * Crée une demande de réservation en attente (RG02 à RG05, RG08).
     *
     * @param  ReservationDemandeRequest  $request
     * @return JsonResponse
     */
    public function store(ReservationDemandeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->demanderReservation(
            $user,
            (int) $request->validated('creneau_id')
        );

        return response()->json([
            'message' => 'Demande de réservation enregistrée en attente. Aucun crédit n\'est bloqué avant acceptation (RG05).',
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Consulte les détails d'une réservation avec coordonnées privées sécurisées (RG19).
     * Seuls les participants de la séance (bénéficiaire, intervenant) et l'administrateur
     * sont autorisés à accéder aux informations.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = Reservation::with(['creneau.offre.intervenant', 'beneficiaire'])->findOrFail($id);

        // RG19 : Seuls les participants légitimes et l'administrateur peuvent consulter la réservation
        if (!$reservation->isParticipant($user) && !$user->isAdministrateur()) {
            return response()->json([
                'message' => 'Action non autorisée. Vous n\'avez pas accès à cette réservation (RG19).',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Accepte une réservation, séquestre les crédits et rejette les demandes concurrentes (RG06, RG07, RG08).
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function accepter(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->accepterReservation($user, $id);

        return response()->json([
            'message' => 'Réservation acceptée. Les crédits ont été placés sous séquestre et les demandes concurrentes refusées (RG06, RG07).',
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Refuse une demande de réservation avec un motif explicite (RG09).
     *
     * @param  ReservationRefusRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function refuser(ReservationRefusRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->refuserReservation(
            $user,
            $id,
            $request->validated('motif')
        );

        return response()->json([
            'message' => 'Demande de réservation refusée (RG09).',
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Permet au demandeur de retirer sa demande en attente (RG09).
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function retirer(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->retirerDemandeEnAttente($user, $id);

        return response()->json([
            'message' => 'Demande de réservation retirée par son auteur (RG09).',
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Annule une réservation acceptée avant séance et restitue les crédits bloqués (RG10).
     *
     * @param  ReservationAnnulationRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function annuler(ReservationAnnulationRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->annulerReservationAcceptee(
            $user,
            $id,
            $request->validated('motif')
        );

        return response()->json([
            'message' => 'Réservation annulée avant séance. Les crédits ont été intégralement restitués (RG10).',
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Confirme la réalisation d'une séance et transfère les crédits lors de la 2ème validation (RG11, RG14).
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function confirmer(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $reservation = $this->reservationService->confirmerRealisation($user, $id);

        $message = $reservation->statut === 'realisee'
            ? 'Séance confirmée par les deux participants ! Les crédits ont été transférés à l\'intervenant (RG11).'
            : 'Votre confirmation a été enregistrée. En attente de la confirmation de l\'autre participant (RG12).';

        return response()->json([
            'message' => $message,
            'reservation' => $this->formatReservationResponse($reservation, $user),
        ], Response::HTTP_OK);
    }

    /**
     * Ouvre un litige sur une séance acceptée après le début de séance (RG12, RG13, RG14).
     *
     * @param  LitigeCreationRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function ouvrirLitige(LitigeCreationRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $litige = $this->reservationService->ouvrirLitige(
            $user,
            $id,
            $request->validated('description')
        );

        return response()->json([
            'message' => 'Litige ouvert avec succès. Les crédits demeurent séquestrés en attente de l\'arbitrage administratif (RG12).',
            'litige' => [
                'id' => $litige->id,
                'reservation_id' => $litige->reservation_id,
                'declarant_id' => $litige->declarant_id,
                'statut' => $litige->statut,
                'description' => $litige->description,
                'created_at' => $litige->created_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Formate la réservation en protégeant les données privées conformément à RG19.
     *
     * @param  Reservation  $reservation
     * @param  User         $user
     * @return array
     */
    protected function formatReservationResponse(Reservation $reservation, User $user): array
    {
        $creneau = $reservation->creneau;
        $offre = $creneau?->offre;

        return [
            'id' => $reservation->id,
            'creneau_id' => $reservation->creneau_id,
            'beneficiaire_id' => $reservation->beneficiaire_id,
            'statut' => $reservation->statut,
            'cout_credits' => $reservation->cout_credits,
            'motif_refus' => $reservation->motif_refus,
            'confirmation_intervenant' => (bool) $reservation->confirmation_intervenant,
            'confirmation_beneficiaire' => (bool) $reservation->confirmation_beneficiaire,
            'date_confirmation_intervenant' => $reservation->date_confirmation_intervenant?->toIso8601String(),
            'date_confirmation_beneficiaire' => $reservation->date_confirmation_beneficiaire?->toIso8601String(),
            'creneau' => $creneau ? [
                'id' => $creneau->id,
                'date' => is_string($creneau->date) ? $creneau->date : $creneau->date->format('Y-m-d'),
                'heure_debut' => $creneau->heure_debut,
                'heure_fin' => $creneau->heure_fin,
                'lieu_public' => $creneau->lieu_public,
                'lieu_ou_lien_prive' => $creneau->getLieuOuLienSecurise($user), // RG19: Protégé !
            ] : null,
            'offre' => $offre ? [
                'id' => $offre->id,
                'titre' => $offre->titre,
                'duree' => $offre->duree,
                'intervenant' => $offre->intervenant ? [
                    'id' => $offre->intervenant->id,
                    'nom' => $offre->intervenant->nom,
                    'prenom' => $offre->intervenant->prenom,
                ] : null,
            ] : null,
            'beneficiaire' => $reservation->beneficiaire ? [
                'id' => $reservation->beneficiaire->id,
                'nom' => $reservation->beneficiaire->nom,
                'prenom' => $reservation->beneficiaire->prenom,
            ] : null,
        ];
    }
}
