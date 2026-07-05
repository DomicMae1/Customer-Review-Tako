/* eslint-disable @typescript-eslint/no-explicit-any */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { BankSupplier, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
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
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Landmark, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { bankSupplierColumns } from './columns';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bank Supplier',
        href: '/bank-supplier',
    },
];

export default function BankSupplierPage() {
    const { suppliers, search: initialSearch, flash } = usePage().props as unknown as {
        suppliers: BankSupplier[];
        search: string;
        flash: { success?: string; error?: string };
    };

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const [searchInput, setSearchInput] = useState(initialSearch ?? '');
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [entitasFilter, setEntitasFilter] = useState<string>('all');

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSearchChange = (value: string) => {
        setSearchInput(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get(
                '/bank-supplier',
                { search: value },
                { preserveState: true, replace: true },
            );
        }, 400);
    };

    const handleEntitasFilterChange = (value: string) => {
        setEntitasFilter(value);
        table.getColumn('entitas')?.setFilterValue(value === 'all' ? undefined : value);
    };

    const table = useReactTable({
        data: suppliers,
        columns: bankSupplierColumns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
        },
        initialState: {
            pagination: { pageSize: 25 },
        },
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank Supplier" />

            <div className="md:p-4">
                {/* Header */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Landmark className="h-5 w-5 text-muted-foreground" />
                        <h1 className="text-lg font-semibold">Bank Supplier</h1>
                        <span className="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {table.getFilteredRowModel().rows.length} data
                        </span>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        {/* Filter Entitas */}
                        <Select value={entitasFilter} onValueChange={handleEntitasFilterChange}>
                            <SelectTrigger className="h-9 w-full sm:w-[180px]" id="bank-supplier-entitas-filter">
                                <SelectValue placeholder="Filter Entitas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Entitas</SelectItem>
                                <SelectItem value="Lengkap">Lengkap</SelectItem>
                                <SelectItem value="Belum Lengkap">Belum Lengkap</SelectItem>
                            </SelectContent>
                        </Select>

                        {/* Search */}
                        <div className="relative w-full sm:w-[280px]">
                            <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="bank-supplier-search"
                                placeholder="Cari nama perusahaan..."
                                value={searchInput}
                                onChange={(e) => handleSearchChange(e.target.value)}
                                className="h-9 pl-8"
                            />
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-x-auto rounded-md border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id} className="whitespace-nowrap bg-muted/50">
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(header.column.columnDef.header, header.getContext())}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows?.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        data-state={row.getIsSelected() && 'selected'}
                                        className="hover:bg-muted/30"
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id} className="whitespace-nowrap py-2">
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={bankSupplierColumns.length}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        {searchInput ? `Tidak ada data untuk "${searchInput}"` : 'Belum ada data supplier.'}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                <div className="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <p className="text-sm text-muted-foreground">
                        Menampilkan{' '}
                        <strong>
                            {table.getState().pagination.pageIndex * table.getState().pagination.pageSize + 1}–
                            {Math.min(
                                (table.getState().pagination.pageIndex + 1) * table.getState().pagination.pageSize,
                                table.getFilteredRowModel().rows.length,
                            )}
                        </strong>{' '}
                        dari <strong>{table.getFilteredRowModel().rows.length}</strong> data
                    </p>

                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => table.setPageIndex(0)}
                            disabled={!table.getCanPreviousPage()}
                            id="bank-supplier-first-page"
                        >
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            id="bank-supplier-prev-page"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="px-2 text-sm">
                            Hal {table.getState().pagination.pageIndex + 1} / {table.getPageCount()}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            id="bank-supplier-next-page"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
                            disabled={!table.getCanNextPage()}
                            id="bank-supplier-last-page"
                        >
                            <ChevronsRight className="h-4 w-4" />
                        </Button>
                    </div>

                    <Select
                        value={String(table.getState().pagination.pageSize)}
                        onValueChange={(value) => table.setPageSize(Number(value))}
                    >
                        <SelectTrigger className="h-8 w-[110px]" id="bank-supplier-page-size">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 25, 50, 100].map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size} / hal
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </AppLayout>
    );
}
