<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $user->load(['skillsProposees', 'skillsRecherchees', 'evaluationsReceived.evaluator']);

        return view('profile.show', compact('user'));
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->update([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'filiere' => $data['filiere'],
            'niveau_etudes' => $data['niveau_etudes'],
        ]);

        // Mise à jour des compétences proposées
        $user->skillsProposees()->delete();
        if (!empty($data['skills_proposees'])) {
            $skillsP = array_filter(array_map('trim', explode(',', $data['skills_proposees'])));
            foreach ($skillsP as $skill) {
                UserSkill::create([
                    'user_id' => $user->id,
                    'competence' => $skill,
                    'type' => 'proposee',
                ]);
            }
        }

        // Mise à jour des compétences recherchées
        $user->skillsRecherchees()->delete();
        if (!empty($data['skills_recherchees'])) {
            $skillsR = array_filter(array_map('trim', explode(',', $data['skills_recherchees'])));
            foreach ($skillsR as $skill) {
                UserSkill::create([
                    'user_id' => $user->id,
                    'competence' => $skill,
                    'type' => 'recherchee',
                ]);
            }
        }

        return redirect()->route('profile.show')
            ->with('success', "Votre profil universitaire et vos compétences ont été mis à jour.");
    }
}
