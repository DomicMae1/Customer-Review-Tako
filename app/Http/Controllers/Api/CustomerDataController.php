<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerDataResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerDataController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->can('customer.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Lacking customer.view permission.',
            ], 403);
        }

        $customers = Customer::query()
            ->select([
                'id',
                'uid',
                'nama_perusahaan',
                'email',
                'no_telp',
                'id_perusahaan',
                'created_at',
            ])
            ->latest('created_at')
            ->paginate(20);

        return CustomerDataResource::collection($customers);
    }
}
