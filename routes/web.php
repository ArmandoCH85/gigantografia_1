<?php
/*
 * RUTAS: Sistema de Rutas de la Aplicación Restaurante
 *
 * CAMBIOS RECIENTES:
 * - Se agregaron nuevas rutas para la visualización individual de reportes
 * - Se implementaron rutas específicas para descarga de Excel por tipo de reporte
 * - Se mejoró la estructura de rutas para los reportes de contabilidad
 * - Se optimizaron las rutas de impresión y generación de comprobantes
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosController;
use App\Livewire\TableMap\TableMapView;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CashRegisterReportController;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use App\Models\CompanyConfig;
use Illuminate\Support\Facades\Blade;
use App\Http\Controllers\PreBillPrintController;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});

// Ruta para redirigir a los usuarios según su rol
Route::get('/delivery-redirect', [\App\Http\Controllers\DeliveryRedirectController::class, 'redirectBasedOnRole'])
    ->name('delivery.redirect')
    ->middleware(['auth']);

// Ruta para exportar detalle de caja a PDF
Route::get('/cash-register-reports/{cashRegister}/export-pdf', [CashRegisterReportController::class, 'exportDetailPdf'])
    ->name('cash.register.reports.export.pdf')
    ->middleware(['auth']); // Asegurar que solo usuarios autenticados puedan acceder

// Ruta para exportar informe de caja a PDF (sin cuadrículas)
Route::get('/admin/export-cash-register-pdf/{id}', [\App\Http\Controllers\Filament\ExportCashRegisterPdfController::class, 'export'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.export-cash-register-pdf');

// Rutas del sistema POS
// Usar el middleware personalizado para verificar el permiso 'access_pos'
Route::middleware(['auth', 'pos.access'])->group(function () {
    Route::get('/pos', [App\Http\Controllers\PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/table/{table}', [PosController::class, 'showTable'])->name('pos.table');

    // Rutas para PDFs - Accesibles para todos los roles autenticados
    Route::get('/pos/command-pdf/{order}', [PosController::class, 'generateCommandPdf'])->name('pos.command.pdf');
    Route::get('/pos/prebill-pdf/{order}', [PosController::class, 'generatePreBillPdf'])->name('pos.prebill.pdf');
    Route::get('/pos/command/generate', [PosController::class, 'createAndShowCommand'])->name('pos.command.generate');
    Route::get('/pos/prebill/generate', [PosController::class, 'createAndShowPreBill'])->name('pos.prebill.generate');

    Route::get('/pos/invoice/generate', [PosController::class, 'createAndShowInvoiceForm'])->name('pos.invoice.create');

    // Ruta para crear orden desde JavaScript
    Route::post('/pos/create-order', [PosController::class, 'createOrderFromJS'])->name('pos.create-order');
});

// Rutas para pagos - Solo cashiers, admin y super_admin
Route::middleware(['auth', 'role:cashier|admin|super_admin'])->group(function () {
    Route::get('/pos/payment/form/{order}', [\App\Http\Controllers\PaymentController::class, 'showPaymentForm'])->name('pos.payment.form');
    Route::post('/pos/payment/process/{order}', [\App\Http\Controllers\PaymentController::class, 'processPayment'])->name('pos.payment.process');
    Route::get('/pos/payment/history/{order}', [\App\Http\Controllers\PaymentController::class, 'showPaymentHistory'])->name('pos.payment.history');
    Route::post('/pos/payment/void/{payment}', [\App\Http\Controllers\PaymentController::class, 'voidPayment'])->name('pos.payment.void');
});

// Rutas para facturación - Solo cashiers, admin y super_admin
Route::middleware(['auth', 'role:cashier|admin|super_admin'])->group(function () {
    Route::get('/pos/invoice/form/{order}', [\App\Http\Controllers\InvoiceController::class, 'showInvoiceForm'])->name('pos.invoice.form');
    Route::post('/pos/invoice/generate/{order}', [\App\Http\Controllers\InvoiceController::class, 'generateInvoice'])->name('pos.invoice.generate');
    Route::get('/pos/invoice/pdf/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'generatePdf'])->name('pos.invoice.pdf');
    Route::post('/invoices/void/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'voidInvoice'])->name('invoices.void');

    // Rutas para vista previa térmica (solo para desarrollo/pruebas)
    Route::get('/thermal-preview/invoice/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'thermalPreview'])->name('thermal.preview.invoice');
});

// Ruta para imprimir ticket (abre PDF directamente) evitando respuesta JSON Livewire
Route::middleware(['auth','role:cashier|admin|super_admin'])->get('/admin/invoices/{invoice}/print-ticket', [\App\Http\Controllers\InvoiceController::class, 'printTicket'])
    ->name('filament.admin.invoices.print-ticket');

// Ruta de impresión de comprobantes - DESHABILITADA - ahora se usa la ruta de Filament
// Route::get('/invoices/print/{invoice}', [\App\Http\Controllers\InvoiceController::class, 'printInvoice'])->name('invoices.print');
Route::get('/thermal-preview/command/{order}', [\App\Http\Controllers\PosController::class, 'thermalPreviewCommand'])->name('thermal.preview.command');
Route::get('/thermal-preview/pre-bill/{order}', [\App\Http\Controllers\PosController::class, 'thermalPreviewPreBill'])->name('thermal.preview.prebill');
Route::get('/thermal-preview/demo', function() {
    return view('thermal-preview');
})->name('thermal.preview.demo');

// Rutas para el proceso unificado de pago y facturación - Solo cashiers, admin y super_admin
Route::middleware(['auth', 'role:cashier|admin|super_admin'])->group(function () {
    Route::get('/pos/unified/{order}', [\App\Http\Controllers\UnifiedPaymentController::class, 'showUnifiedForm'])->name('pos.unified.form');
    Route::post('/pos/unified/process/{order}', [\App\Http\Controllers\UnifiedPaymentController::class, 'processUnified'])->name('pos.unified.process');
});

// Rutas para anulación de comprobantes (legacy) y gestión de clientes
Route::middleware(['auth', 'pos.access'])->group(function () {
    // Rutas para anulación de comprobantes - Solo cashiers, admin y super_admin
    Route::middleware(['role:cashier|admin|super_admin'])->group(function () {
        Route::get('/pos/invoices', [PosController::class, 'invoicesList'])->name('pos.invoices.list');
        Route::get('/pos/invoice/void/{invoice}', [PosController::class, 'showVoidForm'])->name('pos.void.form');
        Route::post('/pos/invoice/void/{invoice}', [PosController::class, 'processVoid'])->name('pos.void.process');
        Route::get('/pos/invoice/void-success/{invoice}', [PosController::class, 'voidSuccess'])->name('pos.void.success');
    });

    // Rutas para gestión de clientes
    Route::get('/pos/customers/find', [PosController::class, 'findCustomer'])->name('pos.customers.find');
    Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])->name('pos.customers.search');
    Route::post('/pos/customers/store', [PosController::class, 'storeCustomer'])->name('pos.customers.store');

    // Rutas para búsqueda de RUC y DNI con Factiliza
    Route::get('/ruc/lookup', [\App\Http\Controllers\RucLookupController::class, 'lookup'])->name('ruc.lookup');
    Route::get('/dni/lookup', [\App\Http\Controllers\RucLookupController::class, 'lookupDni'])->name('dni.lookup');
    Route::get('/ruc/status', [\App\Http\Controllers\RucLookupController::class, 'status'])->name('ruc.status');
    Route::get('/pos/customers/find-enhanced', [\App\Http\Controllers\RucLookupController::class, 'findCustomer'])->name('pos.customers.find.enhanced');
});

// Ruta de prueba para imágenes
Route::get('/test-images', function() {
    $products = \App\Models\Product::whereNotNull('image_path')->limit(5)->get();
    return view('test-images', ['products' => $products]);
});

// Nueva ruta de prueba para imágenes
Route::get('/image-test', function() {
    $products = \App\Models\Product::whereNotNull('image_path')->limit(10)->get();
    return view('image-test', ['products' => $products]);
});

// Rutas del mapa de mesas y delivery
Route::get('/tables', TableMapView::class)
    ->name('tables.map')
    ->middleware(['auth', 'tables.access']);

// La ruta para el mapa de mesas ahora se maneja a través del panel Filament en /admin/mapa-mesas

// Rutas para pedidos de delivery
Route::middleware(['auth', 'delivery.access'])->group(function () {
    Route::get('/delivery/order/{orderId}', [\App\Http\Controllers\DeliveryOrderDetailsController::class, 'show'])->name('delivery.order.details');
    Route::put('/delivery/order/{deliveryOrderId}/update-status', [\App\Http\Controllers\DeliveryOrderDetailsController::class, 'updateStatus'])->name('delivery.update-status');
});

// Nuevas rutas para gestión de delivery
Route::middleware(['auth'])->group(function () {
    // Delivery - Administradores
    Route::get('/delivery/manage', \App\Livewire\Delivery\DeliveryManager::class)
        ->name('delivery.manage')
        ->middleware('role:super_admin|admin');

    // Delivery - Repartidores
    Route::get('/delivery/my-orders', \App\Livewire\Delivery\DeliveryDriver::class)
        ->name('delivery.my-orders')
        ->middleware('role:delivery');
});

// Ruta para resetear el estado de todas las mesas a disponible
Route::get('/tables/reset-status', [\App\Http\Controllers\TableResetController::class, 'resetAllTables'])->name('tables.reset-status');



// Rutas para mantenimiento de mesas
Route::middleware(['auth', 'tables.maintenance.access'])->group(function () {
    Route::get('/tables/maintenance', [TableController::class, 'index'])->name('tables.maintenance');
    Route::get('/tables/create', [TableController::class, 'create'])->name('tables.create');
    Route::post('/tables', [TableController::class, 'store'])->name('tables.store');
    Route::get('/tables/{table}/edit', [TableController::class, 'edit'])->name('tables.edit');
    Route::put('/tables/{table}', [TableController::class, 'update'])->name('tables.update');
    Route::patch('/tables/{table}/update-status', [TableController::class, 'updateStatus'])->name('tables.update-status');
    Route::delete('/tables/{table}', [TableController::class, 'destroy'])->name('tables.destroy');
    // Ruta para comprobantes (comentada hasta resolver el controlador)
    // Route::post('/admin/facturacion/comprobantes', [ComprobanteController::class, 'store'])->name('comprobantes.store');
    // Ruta para el dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
});

// Ruta para impresión de cierre de caja (dentro del panel de Filament)
Route::get('/admin/print-cash-register/{id}', \App\Http\Controllers\Filament\PrintCashRegisterController::class)
    ->middleware(['web', 'auth'])
    ->name('filament.admin.print-cash-register');

// Ruta para aprobar caja registradora
Route::post('/admin/operaciones-caja/approve/{id}', [\App\Http\Controllers\CashRegisterApprovalController::class, 'approve'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.cash-register.approve');

// Rutas para cotizaciones
Route::middleware(['web', 'auth'])->group(function () {
    // Ver PDF de cotización
    Route::get('/admin/quotations/{quotation}/print', [\App\Http\Controllers\Filament\QuotationPdfController::class, 'show'])
        ->name('filament.admin.resources.quotations.print');

    // Descargar PDF de cotización
    Route::get('/admin/quotations/{quotation}/download', [\App\Http\Controllers\Filament\QuotationPdfController::class, 'download'])
        ->name('filament.admin.resources.quotations.download');

    // Enviar cotización por correo electrónico
    Route::post('/admin/quotations/{quotation}/email', [\App\Http\Controllers\Filament\QuotationPdfController::class, 'email'])
        ->name('filament.admin.resources.quotations.email');

    // RUTAS DE PDF DESHABILITADAS - SE USAN LAS PÁGINAS PERSONALIZADAS DE FILAMENT
    // Las rutas están manejadas por:
    // - app/Filament/Resources/InvoiceResource/Pages/PrintInvoice.php
});

// Ruta para descargar archivos temporales (Excel, etc.)
Route::get('/download/temp/{path}', function ($path) {
    $tempDir = sys_get_temp_dir();
    $fullPath = $tempDir . DIRECTORY_SEPARATOR . $path;
    
    // Verificar que el archivo exista y sea un archivo temporal válido
    if (file_exists($fullPath) && strpos($fullPath, $tempDir) === 0) {
        $fileName = request('name', 'archivo_descarga.xlsx');
        
        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ])->deleteFileAfterSend(true);
    }
    
    abort(404, 'Archivo no encontrado');
})->name('download.temp.file')->middleware(['auth']);

// Ruta para descarga directa de Excel de ventas
// Rutas de reportes Excel - Principio KISS: cada reporte con su propio controlador
Route::get('/admin/reportes/sales/excel-download', [\App\Http\Controllers\SalesReportController::class, 'download'])
    ->name('admin.reportes.sales.excel');

Route::get('/admin/reportes/products-by-channel/excel-download', [\App\Http\Controllers\ProductsByChannelReportController::class, 'download'])
    ->name('admin.reportes.products-by-channel.excel');

Route::get('/admin/reportes/purchases/excel-download', [\App\Http\Controllers\PurchaseReportController::class, 'downloadAll'])
    ->name('admin.reportes.purchases.excel');

Route::get('/admin/reportes/payment-methods/excel-download', [\App\Http\Controllers\PaymentMethodReportController::class, 'download'])
    ->name('admin.reportes.payment-methods.excel');

Route::get('/admin/reportes/cash-register/excel-download', [\App\Http\Controllers\CashRegisterReportController::class, 'download'])
    ->name('admin.reportes.cash-register.excel')
    ->middleware(['auth']);

Route::get('/admin/reportes/accounting/excel-download', [\App\Http\Controllers\AccountingReportController::class, 'download'])
    ->name('admin.reportes.accounting.excel')
    ->middleware(['auth']);

// Reporte de caja registradora SIMPLE (para pruebas)
Route::get('/admin/reportes/cash-register/excel-simple', [\App\Http\Controllers\CashRegisterReportSimpleController::class, 'download'])
    ->name('admin.reportes.cash-register.excel-simple')
    ->middleware(['auth']);

// Reporte de caja registradora DEBUG (para examinar archivo) - COMENTADO TEMPORALMENTE
// Route::get('/admin/reportes/cash-register/excel-debug', [\App\Http\Controllers\CashRegisterReportDebugController::class, 'download'])
//     ->name('admin.reportes.cash-register.excel-debug')
//     ->middleware(['auth']);

// Route::get('/download-debug/{filename}', [\App\Http\Controllers\CashRegisterReportDebugController::class, 'downloadFile'])
//     ->name('download.debug')
//     ->middleware(['auth']);





// Rutas para descargas de comprobantes SUNAT
Route::middleware(['web', 'auth'])->group(function () {
    // Descargar XML del comprobante
    Route::get('/admin/invoices/{invoice}/download-xml', [\App\Http\Controllers\InvoiceController::class, 'downloadXml'])
        ->name('filament.admin.invoices.download-xml');

    // Descargar CDR del comprobante
    Route::get('/admin/invoices/{invoice}/download-cdr', [\App\Http\Controllers\InvoiceController::class, 'downloadCdr'])
        ->name('filament.admin.invoices.download-cdr');

    // Descargar PDF del comprobante
    Route::get('/admin/invoices/{invoice}/download-pdf', [\App\Http\Controllers\InvoiceController::class, 'downloadPdf'])
        ->name('filament.admin.invoices.download-pdf');

    // Descargar XML del resumen
    Route::get('/admin/summaries/{summary}/download-xml', [\App\Http\Controllers\SummaryController::class, 'downloadXml'])
        ->name('download.xml');

    // Descargar CDR del resumen
    Route::get('/admin/summaries/{summary}/download-cdr', [\App\Http\Controllers\SummaryController::class, 'downloadCdr'])
        ->name('download.cdr');
});

Route::get('/invoices/{invoice}/download-pdf', function(Invoice $invoice) {
    // Determinar la vista según el tipo de comprobante
    $view = match($invoice->invoice_type) {
        'receipt' => 'pdf.receipt',
        'sales_note' => 'pdf.sales_note',
        default => 'pdf.invoice'
    };

    $pdf = Pdf::loadView($view, compact('invoice'));
    return $pdf->stream("comprobante-{$invoice->id}.pdf");
})->name('invoices.download-pdf');

Route::get('/orders/{order}/download-comanda-pdf', function(Order $order) {
    // ✅ Capturar el nombre del cliente desde la URL para venta directa
    $customerNameForComanda = request()->get('customerName', '');

    // Siempre asegurarse de tener un cliente, incluso si es genérico
    $customer = $order->customer ?? \App\Models\Customer::getGenericCustomer();

    // Verificar que el cliente no sea nulo
    if (!$customer) {
        $customer = \App\Models\Customer::getGenericCustomer();
    }

    $pdf = Pdf::loadView('pdf.comanda', compact('order', 'customerNameForComanda', 'customer'));
    return $pdf->stream("comanda-{$order->id}.pdf");
})->name('orders.comanda.pdf');

// Ruta eliminada para evitar conflicto - se usa la ruta pos.prebill.pdf existente



// Ruta optimizada para pre-cuentas
Route::middleware(['web'])->group(function () {
    Route::get('/print/prebill/{order}', [PreBillPrintController::class, 'show'])
        ->name('print.prebill')
        ->middleware(['auth']);
});

// Ruta optimizada para impresión térmica desde POS (ÚNICA VERSIÓN)
Route::get('/print/invoice/{invoice}', function(Invoice $invoice) {
    // Log para debugging
    Log::info('🖨️ ACCESO A RUTA DE IMPRESIÓN', [
        'invoice_id' => $invoice->id,
        'invoice_type' => $invoice->invoice_type,
        'user_id' => auth()->check() ? auth()->user()->id : null,
        'timestamp' => now()
    ]);

    // Obtener configuración de empresa usando los métodos estáticos
    $company = [
        'ruc' => CompanyConfig::getRuc(),
        'razon_social' => CompanyConfig::getRazonSocial(),
        'nombre_comercial' => CompanyConfig::getNombreComercial(),
        'direccion' => CompanyConfig::getDireccion(),
        'telefono' => CompanyConfig::getTelefono(),
        'email' => CompanyConfig::getEmail(),
    ];

    // Datos para el PDF/HTML
    $invoice->load(['customer', 'details.product', 'order.payments']);

    // Pasar contacto solo para Nota de Venta en venta directa
    $directSaleName = null;
    if ($invoice->invoice_type === 'sales_note' && ($invoice->order) && ($invoice->order->service_type ?? null) !== 'delivery') {
        $directSaleName = session('direct_sale_customer_name');
    }
    
    // ...

    Route::get('/admin/orders/{order}/detail', function($orderId) {
        try {
            $order = \App\Models\Order::with(['customer', 'user', 'orderDetails.product'])->findOrFail($orderId);

            return view('filament.modals.order-detail', compact('order'));
        } catch (\Exception $e) {
            return response('<div class="text-center p-4 text-red-600">Error: ' . $e->getMessage() . '</div>', 404);
        }
    })->name('admin.orders.detail');

    Route::get('/admin/orders/{order}/print', function($orderId) {
        try {
            $order = \App\Models\Order::with(['customer', 'user', 'orderDetails.product'])->findOrFail($orderId);

            return view('filament.modals.order-print', compact('order'));
        } catch (\Exception $e) {
            return response('Error al cargar la orden: ' . $e->getMessage(), 500);
        }
    })->name('admin.orders.print');
});

// 🧪 RUTAS DE PRUEBA SUNAT
Route::middleware(['auth'])->group(function () {
    // Vista principal de prueba SUNAT
    Route::get('/sunat-test', [\App\Http\Controllers\SunatTestController::class, 'index'])
        ->name('sunat-test.index');
    
    // Envío de factura de prueba a SUNAT
    Route::post('/sunat-test/send', [\App\Http\Controllers\SunatTestController::class, 'sendToSunat'])
        ->name('sunat-test.send');
    
    // Obtener logs del sistema en tiempo real
    Route::get('/sunat-test/logs', [\App\Http\Controllers\SunatTestController::class, 'logs'])
        ->name('sunat-test.logs');
});

// 📋 RUTAS DE RESÚMENES DE BOLETAS
Route::middleware(['auth'])->group(function () {
    // Rutas de resúmenes se agregarán aquí
});

// Ruta de login requerida por Laravel para redirecciones de autenticación
Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');



