<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $transactions = $user->creditTransactions()
            ->with('reservation.slot.offer')
            ->paginate(15);

        return view('wallet.index', compact('user', 'transactions'));
    }
}
