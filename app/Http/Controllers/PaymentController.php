<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        $payments = Payment::whereHas('invoice', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->with('invoice')->paginate(10);
        
        return $this->paginated($payments->items(), [
            'page' => $payments->currentPage(),
            'limit' => $payments->perPage(),
            'total' => $payments->total(),
            'totalPages' => $payments->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        // TODO: Implement payment creation
        return $this->error('Not implemented', null, 501);
    }

    public function show($id)
    {
        $payment = Payment::with('invoice')->findOrFail($id);
        return $this->success($payment->toArray());
    }

    public function methods()
    {
        // Return available payment methods
        return $this->success([
            [
                'id' => 'xe_pay',
                'type' => 'digital_wallet',
                'name' => 'XE Pay',
                'icon' => 'xe-pay',
                'enabled' => true,
            ],
            [
                'id' => 'credit_card',
                'type' => 'card',
                'name' => 'Credit Card',
                'icon' => 'credit-card',
                'enabled' => true,
            ],
            [
                'id' => 'bank_transfer',
                'type' => 'bank',
                'name' => 'Bank Transfer',
                'icon' => 'bank',
                'enabled' => true,
            ],
        ]);
    }

    public function createXEPayLink(Request $request)
    {
        // TODO: Implement XE Pay link creation
        return $this->error('Not implemented', null, 501);
    }

    public function xePayStatus(Request $request)
    {
        // TODO: Implement XE Pay status check
        return $this->error('Not implemented', null, 501);
    }
}

