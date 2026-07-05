<?php

namespace App\Http\Controllers;

use App\Models\SupplierAttach;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierAttachController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attachments = SupplierAttach::with('supplier')->latest()->get();
        return response()->json($attachments);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'nama_file'   => 'required|string|max:255',
            'type'        => 'required|in:npwp,sppkp,ktp',
            'file'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $path = $request->file('file')->store('uploads/supplier_attaches', 'public');

        $attachment = SupplierAttach::create([
            'supplier_id' => $request->supplier_id,
            'nama_file'   => $request->nama_file,
            'path'        => $path,
            'type'        => $request->type,
        ]);

        return response()->json(['message' => 'Lampiran berhasil ditambahkan', 'data' => $attachment], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierAttach $supplierAttach)
    {
        // 
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierAttach $supplierAttach)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierAttach $supplierAttach)
    {
        $request->validate([
            'nama_file' => 'sometimes|required|string|max:255',
            'type'      => 'sometimes|required|in:npwp,sppkp,ktp',
            'file'      => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($request->hasFile('file')) {
            if ($supplierAttach->path && Storage::disk('public')->exists($supplierAttach->path)) {
                Storage::disk('public')->delete($supplierAttach->path);
            }

            $supplierAttach->path = $request->file('file')->store('uploads/supplier_attaches', 'public');
        }

        $supplierAttach->nama_file = $request->get('nama_file', $supplierAttach->nama_file);
        $supplierAttach->type      = $request->get('type', $supplierAttach->type);
        $supplierAttach->save();

        return response()->json(['message' => 'Lampiran berhasil diperbarui', 'data' => $supplierAttach]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierAttach $supplierAttach)
    {
        //
    }
}
