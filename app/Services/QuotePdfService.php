<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class QuotePdfService
{
    /**
     * Generate PDF for a quote
     */
    public function generate(Quote $quote): \Barryvdh\DomPDF\PDF
    {
        $quote->load('client', 'items', 'company');
        
        $data = [
            'quote' => $quote,
            'company' => $quote->company,
            'client' => $quote->client,
            'items' => $quote->items,
            'subtotal' => $quote->subtotal,
            'taxAmount' => $quote->tax_amount,
            'total' => $quote->total,
            'validUntil' => $quote->valid_until,
            'createdAt' => $quote->created_at,
        ];

        $pdf = Pdf::loadView('quotes.pdf', $data);
        
        return $pdf;
    }

    /**
     * Generate PDF and return as download response
     */
    public function download(Quote $quote, string $filename = null): \Illuminate\Http\Response
    {
        $filename = $filename ?? 'quote-' . substr($quote->id, 0, 8) . '.pdf';
        
        $pdf = $this->generate($quote);
        
        return $pdf->download($filename);
    }

    /**
     * Generate PDF and return as stream response
     */
    public function stream(Quote $quote): \Illuminate\Http\Response
    {
        $pdf = $this->generate($quote);
        
        return $pdf->stream('quote-' . substr($quote->id, 0, 8) . '.pdf');
    }
}

