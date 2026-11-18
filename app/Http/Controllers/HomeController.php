<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Category;
use App\Models\User;
use App\Models\Reservation;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('offers')->get();
        $recentOffers = Offer::activePublic()
            ->with(['user', 'category', 'availableSlots'])
            ->latest()
            ->take(6)
            ->get();

        $stats = [
            'users_count' => User::where('role', 'etudiant')->where('is_active', true)->count(),
            'offers_count' => Offer::activePublic()->count(),
            'completed_count' => Reservation::where('status', 'completed')->count(),
            'credits_transferred' => abs(CreditTransaction::where('type', 'transfert_credit')->sum('montant')),
        ];

        return view('home', compact('categories', 'recentOffers', 'stats'));
    }
}
