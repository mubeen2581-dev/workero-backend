<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class InvoicePdfService
{
    /**
     * Generate PDF for an invoice
     */
    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load('client', 'items', 'company', 'job');
        
        $data = [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'client' => $invoice->client,
            'items' => $invoice->items,
            'subtotal' => $invoice->amount,
            'taxAmount' => $invoice->tax_amount,
            'total' => $invoice->total,
            'dueDate' => $invoice->due_date,
            'paidDate' => $invoice->paid_date,
            'createdAt' => $invoice->created_at,
            'job' => $invoice->job,
        ];

        $pdf = Pdf::loadView('invoices.pdf', $data);
        
        return $pdf;
    }

    /**
     * Generate PDF and return as download response
     */
    public function download(Invoice $invoice, string $filename = null): \Illuminate\Http\Response
    {
        $filename = $filename ?? 'invoice-' . substr($invoice->id, 0, 8) . '.pdf';
        
        $pdf = $this->generate($invoice);
        
        return $pdf->download($filename);
    }

    /**
     * Generate PDF and return as stream response
     */
    public function stream(Invoice $invoice): \Illuminate\Http\Response
    {
        $pdf = $this->generate($invoice);
        
        return $pdf->stream('invoice-' . substr($invoice->id, 0, 8) . '.pdf');
    }
}

