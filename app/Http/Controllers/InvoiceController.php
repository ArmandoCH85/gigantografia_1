<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\Customer;
use App\Helpers\PdfHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    /**
     * Redirige al formulario unificado de pago y facturación.
     *
     * @param  int  $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showInvoiceForm($orderId)
    {
        // Redirigir al nuevo flujo unificado
        return redirect()->route('pos.unified.form', ['order' => $orderId]);
    }

    /**
     * Redirige al formulario unificado de pago y facturación.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $orderId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateInvoice(Request $request, $orderId)
    {
        // Redirigir al nuevo flujo unificado
        return redirect()->route('pos.unified.form', ['order' => $orderId]);
    }

    /**
     * Muestra una factura para impresión.
     *
     * @param  Invoice  $invoice
     * @return \Illuminate\View\View
     */
    public function printInvoice(Invoice $invoice)
    {
        // Registrar la información del acceso antes de cualquier posible excepción
        $requestId = request('rid', substr(md5(uniqid() . $invoice->id), 0, 8));
        Log::info("🖨️ ACCESO AL CONTROLADOR DE IMPRESIÓN [$requestId]", [
            'invoice_id' => $invoice->id,
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        try {
            // Log detallado para diagnosticar el error 404
            Log::info('🔍 Iniciando impresión de comprobante', [
                'invoice_id' => $invoice->id,
                'invoice_type' => $invoice->invoice_type,
                'series' => $invoice->series,
                'number' => $invoice->number,
                'url' => request()->fullUrl(),
                'referer' => request()->header('referer'),
                'user_id' => auth()->id() ?? 'guest',
                'user_name' => auth()->user()?->name ?? 'guest',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Verificar que la factura existe
            if (!$invoice) {
                Log::error('💥 Factura no encontrada', ['invoice_id' => request()->route('invoice')]);
                abort(404, 'Factura no encontrada');
            }

            // Cargar relaciones necesarias
            $invoice->load([
                'order.orderDetails.product',
                'order.orderDetails.product',
                'order.deliveryOrder',
                'order.employee', // ✅ AGREGAR: Cargar empleado/mesero de la orden
                'order.user',     // ✅ AGREGAR: Cargar usuario de la orden como fallback
                'employee',       // ✅ AGREGAR: Cargar empleado directo de la factura
                'customer',
                'details'
            ]);
        } catch (\Exception $e) {
            Log::error('💥 Error al cargar factura para impresión', [
                'invoice_id' => $invoice->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mostrar un error amigable en lugar de 500
            return response()->view('errors.invoice-not-found', [
                'error' => $e->getMessage()
            ], 500);
        }

        // Obtener la referencia al cliente para usarla en la vista
        $customer = $invoice->customer;

        // ✅ VERIFICACIÓN ADICIONAL: Si no hay customer, usar cliente genérico
        if (!$customer) {
            $customer = Customer::getGenericCustomer();
            Log::warning('🔄 Cliente no encontrado para factura, usando cliente genérico', [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id
            ]);
        }

        // Obtener el cambio/vuelto de la sesión o calcularlo
        $changeAmount = session('change_amount', 0);

        // Si no hay cambio en la sesión, calcularlo basado en los datos de la factura
        if ($changeAmount == 0 && $invoice->payment_method === 'cash' && $invoice->payment_amount > $invoice->total) {
            $changeAmount = $invoice->payment_amount - $invoice->total;
        }

        // Verificar si se debe generar automáticamente la pre-cuenta
        $generatePreBill = session('generate_prebill', false);
        $orderId = session('order_id', null);
        $preBillUrl = null;

        if ($generatePreBill && $orderId && $invoice->order) {
            // Generar la URL de la pre-cuenta para abrir automáticamente
            $preBillUrl = route('pos.prebill.pdf', ['order' => $invoice->order->id]);
        }

        // Limpiar TODAS las variables de sesión relacionadas con el flujo unificado
        session()->forget([
            'generate_prebill',
            'order_id',
            'clear_cart_after_print',
            'table_id'
        ]);

        // Determinar la vista según el tipo de comprobante
        $view = match($invoice->invoice_type) {
            'receipt' => 'pdf.receipt',
            'sales_note' => 'pdf.sales_note',
            default => 'pdf.invoice'
        };

        // Registrar en el log que se está procesando la impresión
        \Illuminate\Support\Facades\Log::info('🖨️ Procesando impresión de comprobante', [
            'invoice_id' => $invoice->id,
            'type' => $invoice->invoice_type,
            'series' => $invoice->series,
            'number' => $invoice->number
        ]);

        // Log de finalización del proceso de impresión
        Log::info('🖨️ COMPROBANTE LISTO PARA IMPRESIÓN', [
            'invoice_id' => $invoice->id,
            'invoice_type' => $invoice->invoice_type,
            'view_template' => $view,
            'customer' => $invoice->customer ? $invoice->customer->name : 'Sin cliente',
            'total' => $invoice->total,
            'status' => $invoice->sunat_status ?? 'N/A',
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
            'process_time_ms' => now()->diffInMilliseconds($invoice->created_at)
        ]);

        return view($view, [
            'invoice' => $invoice,
            'customer' => $customer,
            'change_amount' => $changeAmount,
            'qr_code' => $invoice->qr_code ?? null,
            'prebill_url' => $preBillUrl // URL para generar automáticamente la pre-cuenta
        ]);
    }

    /**
     * Anula una factura.
     *
     * @param  int  $invoiceId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function voidInvoice(Request $request, $invoiceId)
    {
        $request->validate([
            'void_reason' => 'required|string|max:255',
        ]);

        $invoice = Invoice::findOrFail($invoiceId);

        try {
            // Verificar si la factura puede ser anulada
            if (!$invoice->canBeVoided()) {
                return redirect()->route('invoices.print', ['invoice' => $invoice->id])
                    ->with('error', 'Esta factura no puede ser anulada.');
            }

            // Anular la factura
            $result = $invoice->void($request->void_reason);

            if (!$result) {
                return redirect()->route('invoices.print', ['invoice' => $invoice->id])
                    ->with('error', 'No se pudo anular la factura.');
            }

            // Actualizar la orden
            $order = $invoice->order;
            $order->billed = false;
            $order->save();

            return redirect()->route('invoices.print', ['invoice' => $invoice->id])
                ->with('success', 'Factura anulada correctamente.');

        } catch (\Exception $e) {
            Log::error('Error al anular la factura', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId
            ]);

            return redirect()->route('invoices.print', ['invoice' => $invoice->id])
                ->with('error', 'Error al anular la factura: ' . $e->getMessage());
        }
    }

    /**
     * Genera un PDF de la factura.
     *
     * @param  int  $invoiceId
     * @return \Illuminate\View\View
     */
    public function generatePdf($invoiceId)
    {
        $invoice = Invoice::with([
            'order.orderDetails.product',
            'order.orderDetails.product',
            'order.deliveryOrder',
            'order.employee', // ✅ AGREGAR: Cargar empleado/mesero de la orden
            'order.user',     // ✅ AGREGAR: Cargar usuario de la orden como fallback
            'employee',       // ✅ AGREGAR: Cargar empleado directo de la factura
            'customer',
            'details'
        ])->findOrFail($invoiceId);

        // Calcular el cambio/vuelto si es pago en efectivo
        $changeAmount = 0;
        if ($invoice->payment_method === 'cash' && $invoice->payment_amount > $invoice->total) {
            $changeAmount = $invoice->payment_amount - $invoice->total;
        }

        // Determinar la vista según el tipo de comprobante
        $view = match($invoice->invoice_type) {
            'receipt' => 'pdf.receipt',
            'sales_note' => 'pdf.sales_note',
            default => 'pdf.invoice'
        };

        // Obtener la referencia al cliente para usarla en la vista
        $customer = $invoice->customer;

        return view($view, [
            'invoice' => $invoice,
            'customer' => $customer,
            'change_amount' => $changeAmount
        ]);
    }

    /**
     * Vista previa térmica para desarrollo/pruebas.
     *
     * @param  int  $invoiceId
     * @return \Illuminate\View\View
     */
    public function thermalPreview($invoiceId)
    {
        $invoice = \App\Models\Invoice::with([
            'customer',
            'details',
            'details',
            'order.deliveryOrder',
            'order.employee', // ✅ AGREGAR: Cargar empleado/mesero de la orden
            'order.user',     // ✅ AGREGAR: Cargar usuario de la orden como fallback
            'employee'        // ✅ AGREGAR: Cargar empleado directo de la factura
        ])->findOrFail($invoiceId);

        // Determinar la vista según el tipo de comprobante
        $view = match($invoice->invoice_type) {
            'receipt' => 'pdf.receipt',
            'sales_note' => 'pdf.sales_note',
            default => 'pdf.invoice'
        };

        // Obtener la referencia al cliente para usarla en la vista
        $customer = $invoice->customer;

        return view($view, [
            'invoice' => $invoice,
            'customer' => $customer,
            'change_amount' => 0, // Para vista previa
            'thermal_preview' => true // Flag para identificar vista previa
        ]);
    }

    /**
     * Obtiene el siguiente número para un tipo de comprobante.
     *
     * @param string $type Tipo de comprobante (sales_note, receipt, invoice)
     * @return int Siguiente número
     */
    private function getNextNumber(string $type): int
    {
        $lastInvoice = Invoice::where('invoice_type', $type)->latest('number')->first();
        return $lastInvoice ? (int)$lastInvoice->number + 1 : 1;
    }

    /**
     * Descargar XML del comprobante
     */
    public function downloadXml(Invoice $invoice)
    {
        if (!$invoice->xml_path) {
            // Intentar con nombre por defecto
            $documentName = $invoice->series . '-' . $invoice->number;
            $candidate = storage_path('app/private/sunat/xml/' . $documentName . '.xml');
            $altCandidate = storage_path('app/sunat/xml/' . $documentName . '.xml');
            if (File::exists($candidate)) {
                $path = $candidate;
            } elseif (File::exists($altCandidate)) {
                $path = $altCandidate;
            } else {
                abort(404, 'Archivo XML no encontrado');
            }
        } else {
            $path = $invoice->xml_path;
            if (!File::exists($path)) {
                $normalized = ltrim(str_replace(['\\'], ['/' ], $path), '/');
                $candidate = storage_path('app/private/' . $normalized);
                if (File::exists($candidate)) {
                    $path = $candidate;
                } else {
                    // Nuevo: probar también bajo storage/app
                    $altCandidate = storage_path('app/' . $normalized);
                    if (File::exists($altCandidate)) {
                        $path = $altCandidate;
                    } else {
                        // Fallback final por nombre por defecto
                        $documentName = $invoice->series . '-' . $invoice->number;
                        $default1 = storage_path('app/private/sunat/xml/' . $documentName . '.xml');
                        $default2 = storage_path('app/sunat/xml/' . $documentName . '.xml');
                        if (File::exists($default1)) {
                            $path = $default1;
                        } elseif (File::exists($default2)) {
                            $path = $default2;
                        }
                    }
                }
            }

            if (!File::exists($path)) {
                abort(404, 'Archivo XML no encontrado');
            }
        }

        $filename = basename($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Descargar CDR del comprobante
     */
    public function downloadCdr(Invoice $invoice)
    {
        if (!$invoice->cdr_path) {
            // Intentar con nombre por defecto
            $documentName = $invoice->series . '-' . $invoice->number;
            $candidate = storage_path('app/private/sunat/cdr/' . $documentName . '.zip');
            $altCandidate = storage_path('app/sunat/cdr/' . $documentName . '.zip');
            if (File::exists($candidate)) {
                $path = $candidate;
            } elseif (File::exists($altCandidate)) {
                $path = $altCandidate;
            } else {
                abort(404, 'Archivo CDR no encontrado');
            }
        } else {
            $path = $invoice->cdr_path;
            if (!File::exists($path)) {
                $normalized = ltrim(str_replace(['\\'], ['/' ], $path), '/');
                $candidate = storage_path('app/private/' . $normalized);
                if (File::exists($candidate)) {
                    $path = $candidate;
                } else {
                    // Nuevo: probar también bajo storage/app
                    $altCandidate = storage_path('app/' . $normalized);
                    if (File::exists($altCandidate)) {
                        $path = $altCandidate;
                    } else {
                        // Fallback final por nombre por defecto
                        $documentName = $invoice->series . '-' . $invoice->number;
                        $default1 = storage_path('app/private/sunat/cdr/' . $documentName . '.zip');
                        $default2 = storage_path('app/sunat/cdr/' . $documentName . '.zip');
                        if (File::exists($default1)) {
                            $path = $default1;
                        } elseif (File::exists($default2)) {
                            $path = $default2;
                        }
                    }
                }
            }

            if (!File::exists($path)) {
                abort(404, 'Archivo CDR no encontrado');
            }
        }

        $filename = basename($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Descargar PDF del comprobante
     */
    public function downloadPdf(Invoice $invoice)
    {
        // Si existe un PDF generado por SUNAT, descargarlo
        if ($invoice->pdf_path && File::exists($invoice->pdf_path)) {
            $filename = basename($invoice->pdf_path);
            return response()->download($invoice->pdf_path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Si no existe PDF de SUNAT, generar uno usando la vista de impresión
        return $this->generatePdf($invoice->id);
    }

    /**
     * Genera y muestra el PDF/ticket directamente (usado por acciones Filament) evitando serialización Livewire.
     */
    public function printTicket(Invoice $invoice)
    {
        try {
            $invoice->load([
                'customer', 
                'details.product', 
                'details.product',
                'order.employee', // ✅ AGREGAR: Cargar empleado/mesero de la orden
                'order.user',     // ✅ AGREGAR: Cargar usuario de la orden como fallback
                'employee'        // ✅ AGREGAR: Cargar empleado directo de la factura
            ]);

            $company = [
                'ruc' => \App\Models\CompanyConfig::getRuc(),
                'razon_social' => \App\Models\CompanyConfig::getRazonSocial(),
                'nombre_comercial' => \App\Models\CompanyConfig::getNombreComercial(),
                'direccion' => \App\Models\CompanyConfig::getDireccion(),
                'telefono' => \App\Models\CompanyConfig::getTelefono(),
                'email' => \App\Models\CompanyConfig::getEmail(),
            ];

            $data = [
                'invoice' => $invoice,
                'company' => $company,
            ];

            $view = match($invoice->invoice_type) {
                'receipt' => 'pdf.receipt',
                'sales_note' => 'pdf.sales_note',
                default => 'pdf.invoice'
            };

            $items = $invoice->details->count();
            $pdf = PdfHelper::makeTicketPdf($view, $data, $items);
            Log::info('🎫 Ticket PDF generado', [
                'invoice_id' => $invoice->id,
                'type' => $invoice->invoice_type,
                'items' => $items,
            ]);
            return $pdf->stream($invoice->series . '-' . $invoice->number . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Error generando ticket PDF', [
                'invoice_id' => $invoice->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response('Error al generar PDF: ' . $e->getMessage(), 500);
        }
    }
}
