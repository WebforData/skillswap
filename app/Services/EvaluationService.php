<?php

namespace App\Services;

use App\Exceptions\CompteDesactiveException;
use App\Exceptions\DoubleEvaluationException;
use App\Exceptions\EvaluationNonAutoriseeException;
use App\Exceptions\NoteInvalideException;
use App\Exceptions\ReservationNonEvalueeException;
use App\Models\Evaluation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    /**
     * Publie une évaluation bilatérale pour une séance réalisée (RG15).
     *
     * @param  User         $auteur
     * @param  int          $reservationId
     * @param  int          $note
     * @param  string|null  $commentaire
     * @return Evaluation
     *
     * @throws CompteDesactiveException
     * @throws NoteInvalideException
     * @throws EvaluationNonAutoriseeException
     * @throws ReservationNonEvalueeException
     * @throws DoubleEvaluationException
     */
    public function evaluerReservation(User $auteur, int $reservationId, int $note, ?string $commentaire = null): Evaluation
    {
        // 1. RG18 : Vérifier que le compte de l'auteur est actif
        if (!$auteur->isActif()) {
            throw new CompteDesactiveException("Votre compte est désactivé (RG18). Impossible de publier une évaluation.");
        }

        // 2. RG15 : Validation de la note entre 1 et 5
        if ($note < 1 || $note > 5) {
            throw new NoteInvalideException("La note doit être comprise entre 1 et 5 étoiles (RG15).");
        }

        // 3. Exécution sous transaction SQL atomique
        return DB::transaction(function () use ($auteur, $reservationId, $note, $commentaire) {
            // Verrouiller la réservation concernée
            $reservation = Reservation::where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            // Charger les relations nécessaires pour identifier les participants
            $reservation->loadMissing(['creneau.offre.intervenant', 'beneficiaire']);

            // 4. RG15 : Vérifier que l'auteur est bien l'un des participants légitimes
            if (!$reservation->isParticipant($auteur)) {
                throw new EvaluationNonAutoriseeException("Seuls les participants à la séance peuvent déposer une évaluation (RG15).");
            }

            // 5. RG15 : La réservation doit être au statut 'realisee'
            if ($reservation->statut !== 'realisee') {
                throw new ReservationNonEvalueeException("Une évaluation ne peut être déposée que pour une réservation réalisée (RG15). Statut actuel : '{$reservation->statut}'.");
            }

            // 6. RG15 : Déterminer la cible (l'autre participant)
            $cibleId = null;
            if ($reservation->isBeneficiaire($auteur)) {
                $cibleId = $reservation->getIntervenantIdAttribute();
            } elseif ($reservation->isIntervenant($auteur)) {
                $cibleId = $reservation->beneficiaire_id;
            }

            if (!$cibleId) {
                throw new EvaluationNonAutoriseeException("Impossible de déterminer le participant cible pour cette évaluation.");
            }

            // 7. RG15 : Interdiction stricte d'auto-évaluation
            if ((int) $auteur->id === (int) $cibleId) {
                throw new EvaluationNonAutoriseeException("L'auto-évaluation est strictement interdite (RG15).");
            }

            // 8. RG15 : Vérifier sous verrou qu'aucune évaluation n'a déjà été publiée par cet auteur
            $dejaEvalue = Evaluation::where('reservation_id', $reservation->id)
                ->where('auteur_id', $auteur->id)
                ->lockForUpdate()
                ->exists();

            if ($dejaEvalue) {
                throw new DoubleEvaluationException("Vous avez déjà soumis votre évaluation pour cette séance (RG15).");
            }

            // 9. Création de l'évaluation avec protection contre le mass-assignment
            $evaluation = new Evaluation();
            $evaluation->forceFill([
                'reservation_id' => $reservation->id,
                'auteur_id' => $auteur->id,
                'cible_id' => $cibleId,
                'note' => $note,
                'commentaire' => $commentaire ? trim($commentaire) : null,
            ]);
            $evaluation->save();

            return $evaluation->fresh([
                'auteur',
                'cible',
                'reservation.creneau.offre',
            ]);
        });
    }

    /**
     * Récupère les évaluations reçues par un utilisateur avec pagination.
     */
    public function getEvaluationsRecues(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Evaluation::where('cible_id', $user->id)
            ->with(['auteur:id,nom,prenom,filiere', 'reservation.creneau.offre:id,titre,competence'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Récupère les évaluations rédigées par un utilisateur avec pagination.
     */
    public function getEvaluationsDonnees(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Evaluation::where('auteur_id', $user->id)
            ->with(['cible:id,nom,prenom,filiere', 'reservation.creneau.offre:id,titre,competence'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Calcule la note moyenne et le nombre d'évaluations reçues par un étudiant.
     */
    public function getStatistiquesEtudiant(int $etudiantId): array
    {
        $count = Evaluation::where('cible_id', $etudiantId)->count();
        $moyenne = $count > 0 ? round((float) Evaluation::where('cible_id', $etudiantId)->avg('note'), 2) : null;

        return [
            'etudiant_id' => $etudiantId,
            'nombre_evaluations' => $count,
            'note_moyenne' => $moyenne,
        ];
    }
}
