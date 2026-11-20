<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use App\Models\Notification;
use App\Services\AuthService;
use App\Services\SuggestionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function categories()
    {
        return response()->json(['categories' => Categorie::orderBy('nom')->get()]);
    }

    public function updateProfile(Request $request, AuthService $auth)
    {
        $user = $request->user();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'nom' => 'required|string|max:100', 'prenom' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'filiere' => 'nullable|string|max:100',
            'competences_proposees' => 'nullable|string|max:2000', 'competences_recherchees' => 'nullable|string|max:2000',
        ]);
        $data['email'] = strtolower(trim($data['email']));
        $user->update($data);

        return response()->json(['message' => 'Profil enregistré.', 'user' => $auth->formatUserResponse($user->fresh())]);
    }

    public function wallet(Request $request)
    {
        $wallet = $request->user()->portefeuille()->firstOrFail();

        return response()->json($wallet->mouvementsCredits()->with('reservation.creneau.offre:id,titre')->orderByDesc('id')->paginate(15));
    }

    public function suggestions(Request $request, SuggestionService $service)
    {
        return response()->json(['offres' => $service->getSuggestionsForUser($request->user())]);
    }

    public function notifications(Request $request)
    {
        return response()->json(Notification::where('user_id', $request->user()->id)->latest('id')->paginate(20));
    }

    public function readNotification(Request $request, int $id)
    {
        Notification::where('user_id', $request->user()->id)->findOrFail($id)->update(['is_read' => true]);

        return response()->json(['message' => 'Notification lue.']);
    }
}
