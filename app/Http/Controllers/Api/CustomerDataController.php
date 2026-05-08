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
        $customers = Customer::query()
            ->select([
                'id',
                'uid',
                'nama_perusahaan as nama',
                'email',
                'no_telp as phone',
                'id_perusahaan as perusahaan_id',
                'created_at',
            ])
            ->latest('created_at')
            ->paginate(20);

        return CustomerDataResource::collection($customers);
    }
}