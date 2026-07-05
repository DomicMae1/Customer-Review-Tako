/* eslint-disable @typescript-eslint/no-explicit-any */
// Users/table/data-table.tsx
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router, usePage } from '@inertiajs/react';
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
import * as React from 'react';
import { DataTableViewOptions } from './data-table-view-options';
import { DataTablePagination } from './pagination';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

interface Role {
    id: number;
    name: string;
}
interface Perusahaan {
    id: number;
    nama_perusahaan: string;
}

export function DataTable<TData, TValue>({ columns, data }: DataTableProps<TData, TValue>) {
    const { roles, companies } = usePage().props as unknown as {
        // const { roles, auth } = usePage().props as unknown as {
        roles: Role[];
        companies: Perusahaan[];
    };

    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});

    const [openCreate, setOpenCreate] = React.useState(false);
    const [name, setName] = React.useState('');
    const [uid, setUid] = React.useState('');
    const [NIK, setNIK] = React.useState('');
    const [email, setEmail] = React.useState('');
    const [selectedRole, setSelectedRole] = React.useState<string>('');
    const [selectedCompany, setSelectedCompany] = React.useState<string>('');
    const selectedRoleName = roles.find((role) => String(role.id) === selectedRole)?.name;

    const [openImportCsv, setOpenImportCsv] = React.useState(false);
    const [csvFile, setCsvFile] = React.useState<File | null>(null);

    const onSubmitImportCsv = (e: React.FormEvent) => {
        e.preventDefault();

        if (!csvFile) {
            console.error('File CSV harus dipilih.');
            return;
        }

        const formData = new FormData();
        formData.append('csv_file', csvFile);

        router.post('/users/import-csv', formData, {
            forceFormData: true,
            onSuccess: () => {
                setOpenImportCsv(false);
                setCsvFile(null);
            },
            onError: (errors) => {
                console.error('❌ Error saat import CSV:', errors);
            },
        });
    };

    const [filterValue, setFilterValue] = React.useState('');

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
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

    React.useEffect(() => {
        table.getColumn('name')?.setFilterValue(filterValue);
    }, [filterValue, table]);

    const onSubmitCreate = (e: React.FormEvent) => {
        const onlyNumber = (value: string) => value.replace(/\D/g, '');
        e.preventDefault();

        if (!name || !email || !NIK || !selectedRole) {
            console.error('Name, email, NIK, and role are required.');
            return;
        }

        const nikOnlyNumber = onlyNumber(NIK);
        const uidOnlyNumber = onlyNumber(uid);

        if (nikOnlyNumber.length !== 16) {
            console.error('NIK harus 16 digit angka.');
            return;
        }

        if (uidOnlyNumber.length !== 8) {
            console.error('UID harus 8 digit angka.');
            return;
        }

        if (selectedRoleName === 'marketing' && !selectedCompany) {
            console.error('Perusahaan harus dipilih untuk role marketing.');
            return;
        }

        const data = {
            name,
            uid: uidOnlyNumber,
            NIK: nikOnlyNumber,
            email,
            role: selectedRole,
            id_perusahaan: selectedRoleName === 'marketing' ? Number(selectedCompany) : null,
        };

        router.post('/users', data, {
            onSuccess: () => {
                setOpenCreate(false);
                setName('');
                setUid('');
                setNIK('');
                setEmail('');
                setSelectedRole('');
                setSelectedCompany('');
            },
            onError: (errors) => {
                console.error('❌ Error saat menambah user:', errors);
            },
        });
    };

    return (
        <div>
            <div className="flex flex-col gap-2 pb-4 sm:flex-row sm:items-center">
                <div className="w-full sm:w-auto">
                    <Input
                        placeholder="Filter users..."
                        value={filterValue}
                        onChange={(event) => {
                            setFilterValue(event.target.value);
                        }}
                        className="w-full sm:max-w-sm"
                    />
                </div>

                <DataTableViewOptions table={table} />
                <Button className="h-9 w-full sm:w-auto" variant="outline" onClick={() => setOpenImportCsv(true)}>
                    Import from CSV
                </Button>

                <Button className="h-9 w-full sm:w-auto" onClick={() => setOpenCreate(true)}>
                    Add User
                </Button>
            </div>

            {/* Desktop Table */}
            <div className="hidden rounded-md border md:block">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                        </TableHead>
                                    );
                                })}
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

            {/* Mobile Card View */}
            <div className="space-y-3 md:hidden">
                {table.getRowModel().rows?.length ? (
                    table.getRowModel().rows.map((row) => {
                        const user = row.original as any;

                        return (
                            <div key={row.id} className="bg-card rounded-lg border p-4 shadow-sm">
                                <div className="mb-3 flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate font-semibold">{user.name}</h3>
                                        <p className="text-muted-foreground text-sm break-all">{user.email}</p>
                                    </div>

                                    <div>
                                        {row
                                            .getVisibleCells()
                                            .filter((cell) => cell.column.id === 'actions')
                                            .map((cell) => (
                                                <div key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</div>
                                            ))}
                                    </div>
                                </div>

                                <div className="mt-4 grid gap-3 text-sm">
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">UID</p>
                                        <p className="mt-1 font-medium break-all">{user.uid || '-'}</p>
                                    </div>

                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Roles</p>
                                        <p className="mt-1 font-medium">{user.roles?.map((role: any) => role.name).join(', ') || '-'}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-muted-foreground rounded-lg border p-6 text-center text-sm">No results.</div>
                )}
            </div>

            <DataTablePagination table={table} />

            <Dialog
                open={openImportCsv}
                onOpenChange={(open) => {
                    setOpenImportCsv(open);
                    if (!open) {
                        setCsvFile(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import from CSV</DialogTitle>
                        <DialogDescription>Upload file CSV untuk menambahkan user secara massal.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={onSubmitImportCsv} className="space-y-4">
                        <div>
                            <Label htmlFor="csv_file">CSV File</Label>
                            <Input id="csv_file" type="file" accept=".csv,text/csv" onChange={(e) => setCsvFile(e.target.files?.[0] ?? null)} />
                        </div>

                        <DialogFooter className="mt-8 sm:justify-start">
                            <Button type="submit">Import</Button>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={openCreate}
                onOpenChange={(open) => {
                    setOpenCreate(open);
                    if (!open) {
                        setName('');
                        setUid('');
                        setNIK('');
                        setEmail('');
                        setSelectedRole('');
                        setSelectedCompany('');
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add User</DialogTitle>
                        <DialogDescription>Fill in the details to create a new user.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={onSubmitCreate} className="space-y-4">
                        <div>
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Enter name" />
                        </div>
                        <div>
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Enter email" />
                        </div>
                        <div>
                            <Label htmlFor="uid">UID</Label>
                            <Input
                                id="uid"
                                value={uid}
                                inputMode="numeric"
                                maxLength={8}
                                onChange={(e) => setUid(e.target.value.replace(/\D/g, '').slice(0, 8))}
                                placeholder="Masukkan UID 8 digit"
                            />
                            <p className="text-muted-foreground mt-1 text-xs">UID wajib 8 digit angka.</p>
                        </div>

                        <div>
                            <Label htmlFor="NIK">NIK</Label>
                            <Input
                                id="NIK"
                                value={NIK}
                                inputMode="numeric"
                                maxLength={16}
                                onChange={(e) => setNIK(e.target.value.replace(/\D/g, '').slice(0, 16))}
                                placeholder="Masukkan NIK 16 digit"
                            />
                            <p className="text-muted-foreground mt-1 text-xs">NIK wajib 16 digit. Password default memakai 6 digit terakhir NIK.</p>
                        </div>
                        <div>
                            <Label htmlFor="role">Role</Label>
                            <Select onValueChange={setSelectedRole} value={selectedRole}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={String(role.id)}>
                                            {role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {selectedRoleName === 'marketing' && (
                            <div>
                                <Label htmlFor="company">Perusahaan</Label>
                                <Select
                                    onValueChange={(value) => {
                                        setSelectedCompany(value);
                                    }}
                                    value={selectedCompany}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih perusahaan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companies.map((company) => (
                                            <SelectItem key={company.id} value={String(company.id)}>
                                                {company.nama_perusahaan}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <DialogFooter className="mt-8 sm:justify-start">
                            <Button type="submit">Create</Button>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
