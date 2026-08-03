<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierDataResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierDataController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->can('supplier.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Lacking supplier.view permission.',
            ], 403);
        }

        $suppliers = Supplier::with(['perusahaan', 'creator'])
            ->latest('created_at')
            ->paginate(20);

        return SupplierDataResource::collection($suppliers);
    }
}
