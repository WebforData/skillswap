<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Services\CreditWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected CreditWalletService $walletService;

    public function __construct(CreditWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        // Création du compte avec 2 crédits de bienvenue (RG01)
        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'filiere' => $data['filiere'],
            'niveau_etudes' => $data['niveau_etudes'],
            'solde_disponible' => 2, // RG01
            'solde_bloque' => 0,
            'role' => 'etudiant',
            'is_active' => true,
        ]);

        // Historique de transaction de bienvenue (RG01 & RG16)
        $this->walletService->creditWelcomeBonus($user);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', "Bienvenue sur SkillSwap Campus ! Vos 2 crédits de bienvenue ont été versés sur votre compte.");
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            // RG18: Vérifier si le compte est actif
            if (!Auth::user()->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', "Ce compte a été désactivé par l'administration (RG18). Impossible de se connecter.");
            }

            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))
                ->with('success', "Ravi de vous revoir, " . Auth::user()->prenom . " !");
        }

        return back()->withErrors([
            'email' => "Les identifiants fournis ne correspondent à aucun compte universitaire enregistré.",
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('info', "Vous avez été déconnecté avec succès.");
    }
}
