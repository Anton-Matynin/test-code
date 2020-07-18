<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Http\Resources\WalletResource;
use App\Http\Resources\TesterEarningResource;
use App\Http\Resources\WithdrawalResource;

class TransactionHistoriesController extends Controller
{
    public function index()
    {
        $transactions = auth()->user()->withdrawals()->select([
            'id', 'amount', 'balance_after', 'created_at', 'note'
        ])->unionAll(auth()->user()->earnings()->select([
            'id', 'amount', 'balance_after', 'created_at', 'note'
        ]))->latest()->get();
        return TesterEarningResource::collection($transactions);
    }

    public function pendingWithdrawal()
    {
        $withdrawal = auth()->user()->withdrawals()->where('is_paid', false)->first();
        if ($withdrawal) {
            return new WithdrawalResource($withdrawal);
        }
        return response()->json(null);
    }
}
