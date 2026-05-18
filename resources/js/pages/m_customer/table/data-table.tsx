/* eslint-disable @typescript-eslint/no-explicit-any */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, usePage } from '@inertiajs/react';
import {
    ColumnDef,
    ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
    VisibilityState,
} from '@tanstack/react-table';
import axios from 'axios';
import { Copy } from 'lucide-react';
import { nanoid } from 'nanoid';
import * as React from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DataTableViewOptions } from './data-table-view-options';
import { DataTablePagination } from './pagination';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

export function DataTable<TData, TValue>({ columns, data }: DataTableProps<TData, TValue>) {
    const { props } = usePage();
    const auth = props.auth || {};
    const userRole = auth.user?.roles?.[0]?.name ?? '';

    const userHasMainCompany = Boolean(auth.user?.id_perusahaan);

    const userHasCompanies = Array.isArray(auth.user?.companies) && auth.user.companies.length > 0;

    const canAddCustomer = ['user', 'manager', 'direktur'].includes(userRole) && (userHasMainCompany || userHasCompanies);

    const [sorting, setSorting] = React.useState<SortingState>([{ id: 'keterangan_status', desc: true }]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [hasUserSorted, setHasUserSorted] = React.useState(false);

    const [isNameDialogOpen, setIsNameDialogOpen] = useState(false);
    const [isLinkDialogOpen, setIsLinkDialogOpen] = useState(false);
    const [customerName, setCustomerName] = useState('');
    const [generatedLink, setGeneratedLink] = useState('');
    const [statusFilter, setStatusFilter] = useState<'sudah' | 'belum' | ''>('');

    const [filterColumn, setFilterColumn] = useState<'nama_customer' | 'creator_name' | 'nama_perusahaan' | 'keterangan_status' | 'status'>(
        'creator_name',
    );

    const [filterValue, setFilterValue] = useState('');
    const isKeteranganStatus = filterColumn === 'keterangan_status';
    const isStatusReview = filterColumn === 'status';
    const isCreatorName = filterColumn === 'creator_name';

    const creatorNames = Array.from(new Set(data.map((item: any) => item.creator_name).filter(Boolean)));
    const [selectedPerusahaanId, setSelectedPerusahaanId] = useState<string>('');

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: (updater) => {
            setHasUserSorted(true);
            setSorting(updater);
        },
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
        },
    });

    useEffect(() => {
        const column = table.getColumn(filterColumn);
        if (!column) return;

        column.setFilterValue(filterValue === '' ? undefined : filterValue);
    }, [filterValue, filterColumn, table]);

    useEffect(() => {
        const column = table.getColumn('status_2_timestamps');
        if (!column) return;

        if (statusFilter === 'sudah' || statusFilter === 'belum') {
            column.setFilterValue(statusFilter);
        } else {
            column.setFilterValue(undefined);
        }
    }, [statusFilter, table]);

    const handleReset = () => {
        table.resetColumnFilters();
        setColumnFilters([]);
        setFilterValue('');
        table.resetSorting();
        setSorting([{ id: 'keterangan_status', desc: true }]);
        setHasUserSorted(false);
        setFilterColumn('creator_name');
    };

    const handleSubmitName = async () => {
        if (!customerName.trim()) {
            toast.error('Nama customer tidak boleh kosong.');
            return;
        }

        let id_perusahaan = selectedPerusahaanId;

        if (userRole === 'user') {
            id_perusahaan = auth.user?.id_perusahaan?.toString() || '';
        }

        if (!id_perusahaan) {
            toast.error('ID Perusahaan tidak valid.');
            return;
        }

        const token = nanoid(12);

        try {
            const res = await axios.post(route('customer-links.store'), {
                nama_customer: customerName,
                token,
                id_perusahaan,
            });

            setGeneratedLink(res.data.link);
            setIsNameDialogOpen(false);
            setIsLinkDialogOpen(true);
            setCustomerName('');

            toast.success('Link berhasil dibuat.');
        } catch (error: any) {
            console.error('Gagal membuat link:', error);

            toast.error(error?.response?.data?.message ?? 'Terjadi kesalahan saat membuat link.');
        }
    };

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(generatedLink);
            toast.success('Link disalin ke clipboard!');
        } catch (error) {
            console.error('Gagal menyalin link:', error);
            toast.error('Gagal menyalin link.');
        }
    };

    return (
        <div>
            <div className="flex hidden items-center gap-2 pb-4 md:block">
                <div className="flex gap-2">
                    <Select value={filterColumn} onValueChange={(val) => setFilterColumn(val as any)}>
                        <SelectTrigger className="w-[250px]">
                            <SelectValue placeholder="Pilih Kolom" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="nama_perusahaan">Ownership</SelectItem>
                            <SelectItem value="creator_name">Disubmit Oleh</SelectItem>
                            <SelectItem value="nama_customer">Nama Customer</SelectItem>
                            <SelectItem value="keterangan_status">Keterangan Status</SelectItem>
                            <SelectItem value="status">Status Review</SelectItem>
                        </SelectContent>
                    </Select>

                    {isKeteranganStatus ? (
                        <Select value={filterValue} onValueChange={(val) => setFilterValue(val)}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Pilih Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="diinput">Diinput</SelectItem>
                                <SelectItem value="disubmit">Disubmit</SelectItem>
                                <SelectItem value="diverifikasi">Diverifikasi</SelectItem>
                                <SelectItem value="diketahui">Diketahui</SelectItem>
                                <SelectItem value="direview">Direview</SelectItem>
                            </SelectContent>
                        </Select>
                    ) : isStatusReview ? (
                        <Select value={filterValue} onValueChange={(val) => setFilterValue(val)}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Pilih Review Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="approved">Aman</SelectItem>
                                <SelectItem value="rejected">Bermasalah</SelectItem>
                            </SelectContent>
                        </Select>
                    ) : isCreatorName ? (
                        <Select value={filterValue} onValueChange={(val) => setFilterValue(val)}>
                            <SelectTrigger className="w-[250px]">
                                <SelectValue placeholder="Pilih Creator" />
                            </SelectTrigger>

                            <SelectContent>
                                {creatorNames.map((name) => (
                                    <SelectItem key={name} value={name}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            placeholder="Ketik kata kunci..."
                            value={filterValue}
                            onChange={(event) => setFilterValue(event.target.value)}
                            className="max-w-sm"
                        />
                    )}
                    <Button variant="outline" className="h-auto" onClick={handleReset}>
                        Reset
                    </Button>

                    {userRole === 'direktur' && (
                        <div>
                            <Select value={statusFilter} onValueChange={(val) => setStatusFilter(val as 'sudah' | 'belum' | 'all')}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua</SelectItem>
                                    <SelectItem value="sudah">Sudah Mengetahui</SelectItem>
                                    <SelectItem value="belum">Belum Mengetahui</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                <div className="flex gap-2">
                    <DataTableViewOptions table={table} />
                    {canAddCustomer && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button className="h-9">Add customer</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Pilih Metode</DialogTitle>
                                    <DialogDescription>
                                        Apakah Anda ingin membagikan formulir ke customer, atau isi sendiri di sini?
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="flex flex-col gap-4 py-4">
                                    <Link href="/customer/create?mode=manual">
                                        <Button className="w-full">Buat Sendiri</Button>
                                    </Link>
                                    <Button variant="outline" className="w-full" onClick={() => setIsNameDialogOpen(true)}>
                                        Bagikan ke Customer
                                    </Button>
                                </div>
                                <DialogFooter>
                                    <p className="text-muted-foreground text-xs">Anda dapat mengubah pilihan ini nanti.</p>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
            </div>

            <div className="hidden rounded-md border md:block">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                            <button className="flex items-center gap-1" onClick={() => header.column.toggleSorting()}>
                                                {flexRender(header.column.columnDef.header, header.getContext())}

                                                {hasUserSorted &&
                                                    (header.column.getIsSorted() === 'asc'
                                                        ? '⬆️'
                                                        : header.column.getIsSorted() === 'desc'
                                                          ? '⬇️'
                                                          : '')}
                                            </button>
                                        ) : (
                                            <div className="flex cursor-default items-center gap-1 select-none">
                                                {flexRender(header.column.columnDef.header, header.getContext())}
                                            </div>
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} data-state={row.getIsSelected() && 'selected'}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* === FILTERS MOBILE === */}
            <div className="flex w-full flex-col gap-3 px-3 py-3 md:hidden">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Select value={filterColumn} onValueChange={(v) => setFilterColumn(v as any)}>
                        <SelectTrigger className="h-9 w-full px-2 text-sm">
                            <SelectValue placeholder="Kolom" />
                        </SelectTrigger>
                        <SelectContent className="text-sm">
                            <SelectItem value="nama_perusahaan">Ownership</SelectItem>
                            <SelectItem value="creator_name">Disubmit Oleh</SelectItem>
                            <SelectItem value="nama_customer">Nama Customer</SelectItem>
                            <SelectItem value="keterangan_status">Keterangan Status</SelectItem>
                            <SelectItem value="status">Review</SelectItem>
                        </SelectContent>
                    </Select>

                    {isKeteranganStatus ? (
                        <Select value={filterValue} onValueChange={setFilterValue}>
                            <SelectTrigger className="h-9 w-full px-2 text-sm">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent className="text-sm">
                                <SelectItem value="diinput">Diinput</SelectItem>
                                <SelectItem value="disubmit">Disubmit</SelectItem>
                                <SelectItem value="diverifikasi">Diverifikasi</SelectItem>
                                <SelectItem value="diketahui">Diketahui</SelectItem>
                                <SelectItem value="direview">Direview</SelectItem>
                            </SelectContent>
                        </Select>
                    ) : isStatusReview ? (
                        <Select value={filterValue} onValueChange={setFilterValue}>
                            <SelectTrigger className="h-9 w-full px-2 text-sm">
                                <SelectValue placeholder="Review" />
                            </SelectTrigger>
                            <SelectContent className="text-sm">
                                <SelectItem value="approved">Aman</SelectItem>
                                <SelectItem value="rejected">Bermasalah</SelectItem>
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            placeholder="Kata kunci..."
                            value={filterValue}
                            onChange={(e) => setFilterValue(e.target.value)}
                            className="h-9 w-full px-2 text-sm"
                        />
                    )}

                    {userRole === 'direktur' && (
                        <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v as any)}>
                            <SelectTrigger className="h-9 w-full px-2 text-sm">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent className="text-sm">
                                <SelectItem value="all">Semua</SelectItem>
                                <SelectItem value="sudah">Sudah Mengetahui</SelectItem>
                                <SelectItem value="belum">Belum Mengetahui</SelectItem>
                            </SelectContent>
                        </Select>
                    )}

                    <Button variant="outline" onClick={handleReset} className="h-9 w-full text-sm">
                        Reset
                    </Button>
                </div>

                {/* Action Bar */}
                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <div className="text-sm">
                        <DataTableViewOptions table={table} />
                    </div>

                    {canAddCustomer && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button className="h-9 w-full text-sm sm:w-auto">Add Customer</Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Pilih Metode</DialogTitle>
                                    <DialogDescription>Pilih metode pengisian data.</DialogDescription>
                                </DialogHeader>

                                <div className="flex flex-col gap-3 py-2">
                                    <Link href="/customer/create?mode=manual">
                                        <Button className="h-9 w-full text-sm">Buat Sendiri</Button>
                                    </Link>
                                    <Button variant="outline" className="h-9 w-full text-sm" onClick={() => setIsNameDialogOpen(true)}>
                                        Bagikan ke Customer
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
            </div>

            {/* === MOBILE CARD LIST === */}
            <div className="grid w-full grid-cols-1 gap-3 px-3 md:hidden">
                {table.getRowModel().rows?.length ? (
                    table.getRowModel().rows.map((row) => {
                        const cells = row.getVisibleCells();

                        return (
                            <div key={row.id} className="rounded-lg border bg-white p-4 shadow-sm dark:border-white dark:bg-black">
                                <div className="space-y-3">
                                    {cells
                                        .filter((c) => c.column.id !== 'select' && c.column.id !== 'actions')
                                        .map((cell) => (
                                            <div key={cell.id} className="flex flex-col gap-0.5">
                                                {/* LABEL */}
                                                <p className="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                                    {flexRender(cell.column.columnDef.header, cell.getContext())}
                                                </p>

                                                {/* VALUE */}
                                                <p className="text-sm font-medium break-words text-gray-900 dark:text-white">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </p>
                                            </div>
                                        ))}

                                    {/* ACTIONS */}
                                    {(() => {
                                        const action = cells.find((c) => c.column.id === 'actions');
                                        if (!action) return null;

                                        return <div>{flexRender(action.column.columnDef.cell, action.getContext())}</div>;
                                    })()}
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="rounded-lg border bg-white p-4 text-center text-gray-500 dark:bg-gray-800 dark:text-gray-400">No results.</div>
                )}
            </div>

            <DataTablePagination table={table} />
            <Dialog open={isNameDialogOpen} onOpenChange={setIsNameDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        {auth.user?.roles?.some((role: any) => ['manager', 'direktur'].includes(role.name)) && (
                            <>
                                <DialogTitle>Pilih perusahaan yang ingin dituju</DialogTitle>
                                <div className="mb-6 flex flex-col gap-4">
                                    <div>
                                        <DialogDescription>Perusahaan</DialogDescription>
                                        <Select value={selectedPerusahaanId} onValueChange={(value) => setSelectedPerusahaanId(value)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih Perusahaan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {auth.user?.companies?.map((perusahaan: any) => (
                                                    <SelectItem key={perusahaan.id} value={String(perusahaan.id)}>
                                                        {perusahaan.nama_perusahaan}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </>
                        )}
                        <DialogTitle>Masukkan Nama Customer</DialogTitle>
                        <DialogDescription>Nama ini akan digunakan untuk membuat link unik.</DialogDescription>
                        <Input
                            className="mb-6"
                            value={customerName}
                            onChange={(e) => setCustomerName(e.target.value)}
                            placeholder="Contoh: Budi Santoso"
                        />
                        <Button onClick={handleSubmitName}>Submit</Button>
                    </DialogHeader>
                </DialogContent>
            </Dialog>

            <Dialog open={isLinkDialogOpen} onOpenChange={setIsLinkDialogOpen}>
                <DialogContent className="max-h-[90dvh] w-[calc(100vw-32px)] max-w-[calc(100vw-32px)] overflow-hidden rounded-xl p-0 sm:max-w-xl md:max-w-2xl lg:max-w-3xl">
                    <div className="max-h-[90dvh] overflow-y-auto p-4 sm:p-6">
                        <DialogHeader className="space-y-2 text-left">
                            <DialogTitle className="pr-8 text-base font-semibold sm:text-lg">Link Berhasil Dibuat</DialogTitle>
                            <DialogDescription className="text-sm leading-relaxed">Salin link berikut dan kirimkan ke customer.</DialogDescription>
                        </DialogHeader>

                        <div className="mt-5 rounded-lg border bg-gray-50 p-3 dark:bg-neutral-900">
                            <div className="flex items-center gap-2">
                                <div className="min-w-0 flex-1 rounded-md bg-white px-3 py-2 dark:bg-neutral-950">
                                    <div className="w-full overflow-x-auto overflow-y-hidden pb-1">
                                        <p className="w-max text-xs leading-6 whitespace-nowrap text-gray-700 sm:text-sm dark:text-gray-200">
                                            {generatedLink}
                                        </p>
                                    </div>
                                </div>

                                <Button type="button" onClick={handleCopy} variant="outline" className="h-10 shrink-0 gap-2 px-3">
                                    <Copy className="h-4 w-4" />
                                    <span className="hidden sm:inline">Copy</span>
                                </Button>
                            </div>
                        </div>

                        <div className="mt-5 flex justify-end">
                            <Button
                                type="button"
                                onClick={() => setIsLinkDialogOpen(false)}
                                className="w-full bg-green-600 hover:bg-green-700 sm:w-auto"
                            >
                                Tutup
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
