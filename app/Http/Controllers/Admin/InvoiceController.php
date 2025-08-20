<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;


class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum','role:admin']);
    }

    public function index(): JsonResponse
    {
        $invoices = Invoice::with('client','admin')->latest()->paginate(20);
        return response()->json($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data) {
            $items = $data['items'] ?? null;

            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'],
                'user_id' => $data['user_id'],
                'admin_id' => Auth::id(), // Use Auth facade instead of request->user()
                'amount' => 0, // recalculé plus bas
                'due_date' => $data['due_date'] ?? null,
                'meta' => $data['meta'] ?? null,
                'status' => $data['status'] ?? 'pending',
            ]);

            $total = 0;
            if (!empty($items)) {
                foreach ($items as $it) {
                    $lineTotal = $it['qty'] * $it['unit_price'];
                    $invoice->items()->create([
                        'description' => $it['description'],
                        'qty' => $it['qty'],
                        'unit_price' => $it['unit_price'],
                        'total' => $lineTotal,
                    ]);
                    $total += $lineTotal;
                }
            }

            // si montant envoyé, vérifier ou remplacer; sinon utiliser somme des items
            $finalAmount = $data['amount'] ?? $total;
            if ($finalAmount == 0 && $total > 0) {
                $finalAmount = $total;
            }

            $invoice->amount = $finalAmount;
            $invoice->save();

            return $invoice->load('items','client');
        });

        return response()->json($invoice, 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load('items','client','admin'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'invoice_number' => $data['invoice_number'],
                'user_id' => $data['user_id'],
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'meta' => $data['meta'] ?? $invoice->meta,
                'status' => $data['status'] ?? $invoice->status,
            ]);

            if (!empty($data['items'])) {
                // supprimer les anciennes lignes puis recréer (simple)
                $invoice->items()->delete();
                $total = 0;
                foreach ($data['items'] as $it) {
                    $lineTotal = $it['qty'] * $it['unit_price'];
                    $invoice->items()->create([
                        'description' => $it['description'],
                        'qty' => $it['qty'],
                        'unit_price' => $it['unit_price'],
                        'total' => $lineTotal,
                    ]);
                    $total += $lineTotal;
                }
                $invoice->amount = $total;
                $invoice->save();
            } elseif (isset($data['amount'])) {
                $invoice->amount = $data['amount'];
                $invoice->save();
            }
        });

        return response()->json($invoice->fresh()->load('items'));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();
        return response()->json(['message' => 'Invoice deleted']);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load('items','client','admin');
        $data = ['invoice' => $invoice];
        $pdf = Pdf::loadView('pdf.invoice', $data);
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}