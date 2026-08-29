<?php 
namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrdersController extends Controller
{
    public function index()
    {
        $this->authorize('view', PurchaseOrder::class);
        return view('purchase_orders/index');
    }

    public function create()
    {
        $this->authorize('create', PurchaseOrder::class);
        return view('purchase_orders/edit')
            ->with('item', new PurchaseOrder);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = new PurchaseOrder();
        $po->fill($request->all());
        $po->created_by = auth()->id();

        if ($po->save()) {
            return redirect()
                ->route('purchase-orders.index')
                ->with('success', 'Purchase Order created successfully');
        }

        return back()->withErrors($po->getErrors());
    }

    public function edit($id)
    {
        $item = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $item);

        // Note: the shared edit.blade.php view (used for both create and edit)
        // expects the model in an `$item` variable, matching create() above and
        // the convention used by every other module's edit form (suppliers,
        // licenses, etc). Previously this passed `$po`, which the view never
        // received, since edit.blade.php never referenced that name.
        return view('purchase_orders/edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $po);

        $po->fill($request->all());

        if ($po->save()) {
            return redirect()
                ->route('purchase-orders.index')
                ->with('success', 'Purchase Order updated successfully');
        }

        return back()->withErrors($po->getErrors());
    }

    public function show($id)
    {
        $po = PurchaseOrder::with('vendor','requestedBy','owner','assets')
            ->findOrFail($id);

        $this->authorize('view', $po);
        return view('purchase_orders/view', compact('po'));
    }
}
