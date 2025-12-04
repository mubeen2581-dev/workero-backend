<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use App\Models\Job;
use App\Models\Quote;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $query = Invoice::where('company_id', $companyId)->with('client', 'job', 'items');
        
        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->input('status'));
        }
        
        // Filter by client
        if ($request->has('client_id') && $request->client_id !== '') {
            $query->where('client_id', $request->input('client_id'));
        }
        
        // Filter by job
        if ($request->has('job_id') && $request->job_id !== '') {
            $query->where('job_id', $request->input('job_id'));
        }
        
        // Search
        if ($request->has('search') && $request->search !== '') {
            $search = $request->input('search');
            $query->whereHas('client', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('notes', 'like', "%{$search}%");
        }
        
        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        $perPage = $request->input('per_page', 10);
        $invoices = $query->paginate($perPage);
        
        return $this->paginated($invoices->items(), [
            'page' => $invoices->currentPage(),
            'limit' => $invoices->perPage(),
            'total' => $invoices->total(),
            'totalPages' => $invoices->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|uuid|exists:clients,id',
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'required|date',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }
        
        // Verify client belongs to company
        $client = Client::where('company_id', $companyId)->findOrFail($request->client_id);
        
        // Verify job belongs to company if provided
        if ($request->has('job_id') && $request->job_id) {
            $job = Job::where('company_id', $companyId)->findOrFail($request->job_id);
        }
        
        try {
            DB::beginTransaction();
            
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            
            foreach ($request->items as $item) {
                $quantity = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $lineTax;
                
                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }
            
            $total = $subtotal + $taxAmount;
            
            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => $request->client_id,
                'job_id' => $request->job_id ?? null,
                'amount' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'currency' => $request->currency ?? 'GBP',
                'status' => 'draft',
                'due_date' => $request->due_date,
                'notes' => $request->notes,
            ]);
            
            // Create invoice items
            foreach ($request->items as $item) {
                $quantity = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $lineTax;
                
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);
            }
            
            DB::commit();
            
            return $this->success(
                $invoice->fresh()->load('client', 'job', 'items')->toArray(),
                'Invoice created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create invoice: ' . $e->getMessage(), null, 500);
        }
    }

    public function show($id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)
            ->with('client', 'job', 'items', 'payments')
            ->findOrFail($id);
        
        return $this->success($invoice->toArray());
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        // Don't allow updating paid or cancelled invoices
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return $this->error('Cannot update paid or cancelled invoices', null, 400);
        }
        
        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|uuid|exists:clients,id',
            'job_id' => 'nullable|uuid|exists:jobs,id',
            'items' => 'sometimes|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'sometimes|date',
            'currency' => 'nullable|string|size:3',
            'status' => 'sometimes|in:draft,sent,paid,overdue,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Update invoice fields
            if ($request->has('client_id')) {
                $client = Client::where('company_id', $companyId)->findOrFail($request->client_id);
                $invoice->client_id = $request->client_id;
            }
            
            if ($request->has('job_id')) {
                if ($request->job_id) {
                    $job = Job::where('company_id', $companyId)->findOrFail($request->job_id);
                }
                $invoice->job_id = $request->job_id;
            }
            
            if ($request->has('due_date')) {
                $invoice->due_date = $request->due_date;
            }
            
            if ($request->has('currency')) {
                $invoice->currency = $request->currency;
            }
            
            if ($request->has('status')) {
                $invoice->status = $request->status;
            }
            
            if ($request->has('notes')) {
                $invoice->notes = $request->notes;
            }
            
            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $invoice->items()->delete();
                
                // Calculate totals
                $subtotal = 0;
                $taxAmount = 0;
                
                foreach ($request->items as $item) {
                    $quantity = floatval($item['quantity']);
                    $unitPrice = floatval($item['unit_price']);
                    $taxRate = floatval($item['tax_rate'] ?? 0);
                    
                    $lineSubtotal = $quantity * $unitPrice;
                    $lineTax = $lineSubtotal * ($taxRate / 100);
                    $lineTotal = $lineSubtotal + $lineTax;
                    
                    $subtotal += $lineSubtotal;
                    $taxAmount += $lineTax;
                    
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'tax_rate' => $taxRate,
                        'line_total' => $lineTotal,
                    ]);
                }
                
                $total = $subtotal + $taxAmount;
                
                $invoice->amount = $subtotal;
                $invoice->tax_amount = $taxAmount;
                $invoice->total = $total;
            }
            
            $invoice->save();
            
            DB::commit();
            
            return $this->success(
                $invoice->fresh()->load('client', 'job', 'items')->toArray(),
                'Invoice updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update invoice: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroy($id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        // Don't allow deleting paid invoices
        if ($invoice->status === 'paid') {
            return $this->error('Cannot delete paid invoices', null, 400);
        }
        
        $invoice->delete();
        
        return $this->success(null, 'Invoice deleted successfully');
    }

    public function send($id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        // Update status to 'sent'
        $invoice->status = 'sent';
        $invoice->save();
        
        // TODO: Implement sending invoice via email/WhatsApp
        // For now, just update the status
        
        return $this->success(
            $invoice->fresh()->load('client', 'job', 'items')->toArray(),
            'Invoice sent successfully'
        );
    }

    public function pay(Request $request, $id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        if ($invoice->status === 'paid') {
            return $this->error('Invoice is already paid', null, 400);
        }
        
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash,card,bank_transfer,xe_pay',
            'amount' => 'nullable|numeric|min:0.01',
        ]);
        
        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }
        
        $paymentAmount = $request->input('amount', $invoice->total);
        
        // TODO: Implement payment processing via XE Pay
        // For now, just mark as paid
        
        $invoice->status = 'paid';
        $invoice->paid_date = Carbon::now();
        $invoice->payment_method = $request->payment_method;
        $invoice->save();
        
        return $this->success(
            $invoice->fresh()->load('client', 'job', 'items', 'payments')->toArray(),
            'Payment processed successfully'
        );
    }

    /**
     * Generate invoice from job
     */
    public function generateFromJob(Request $request, $jobId)
    {
        $companyId = $this->getCompanyId();
        $job = Job::where('company_id', $companyId)
            ->with('client', 'quote')
            ->findOrFail($jobId);
        
        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date',
            'items' => 'sometimes|array|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }
        
        try {
            DB::beginTransaction();
            
            $items = $request->input('items', []);
            
            // If no items provided and job has a quote, use quote items
            if (empty($items) && $job->quote) {
                $quote = Quote::with('items')->find($job->quote->id);
                foreach ($quote->items as $quoteItem) {
                    $items[] = [
                        'description' => $quoteItem->description,
                        'quantity' => $quoteItem->quantity,
                        'unit_price' => $quoteItem->unit_price,
                        'tax_rate' => 20, // Default VAT rate
                    ];
                }
            }
            
            // If still no items, create default item from job
            if (empty($items)) {
                $items[] = [
                    'description' => $job->title ?? 'Job Service',
                    'quantity' => 1,
                    'unit_price' => $job->actual_cost ?? $job->estimated_cost ?? 0,
                    'tax_rate' => 20, // Default VAT rate
                ];
            }
            
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            
            foreach ($items as $item) {
                $quantity = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);
                $taxRate = floatval($item['tax_rate'] ?? 20);
                
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                
                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }
            
            $total = $subtotal + $taxAmount;
            
            // Create invoice
            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => $job->client_id,
                'job_id' => $job->id,
                'amount' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'currency' => 'GBP',
                'status' => 'draft',
                'due_date' => $request->due_date,
                'notes' => $request->notes ?? 'Invoice generated from job',
            ]);
            
            // Create invoice items
            foreach ($items as $item) {
                $quantity = floatval($item['quantity']);
                $unitPrice = floatval($item['unit_price']);
                $taxRate = floatval($item['tax_rate'] ?? 20);
                
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $lineTax;
                
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);
            }
            
            DB::commit();
            
            return $this->success(
                $invoice->fresh()->load('client', 'job', 'items')->toArray(),
                'Invoice generated from job successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to generate invoice from job: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Download invoice as PDF
     */
    public function downloadPdf($id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        $pdfService = new InvoicePdfService();
        $filename = 'invoice-' . substr($invoice->id, 0, 8) . '.pdf';
        
        return $pdfService->download($invoice, $filename);
    }

    /**
     * Stream invoice PDF
     */
    public function streamPdf($id)
    {
        $companyId = $this->getCompanyId();
        $invoice = Invoice::where('company_id', $companyId)->findOrFail($id);
        
        $pdfService = new InvoicePdfService();
        
        return $pdfService->stream($invoice);
    }
}

