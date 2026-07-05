/* eslint-disable @typescript-eslint/no-explicit-any */
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
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

// ─── Same label / style maps as page.tsx ────────────────────────────────────
const PERMISSION_LABELS: Record<string, string> = {
    'customer.view': 'Lihat Customer',
    'customer.create': 'Tambah Customer',
    'customer.update': 'Edit Customer',
    'customer.delete': 'Hapus Customer',
    'customer.pdf': 'Download PDF',
    'customer.import': 'Import CSV',
    'customer.link.create': 'Buat Link Publik',
    'customer.approve.manager': 'Approve (Manager)',
    'customer.approve.direktur': 'Approve (Direktur)',
    'customer.approve.lawyer': 'Approve (Lawyer)',
    'customer.approve.auditor': 'Review (Auditor)',
    'perusahaan.view': 'Lihat Perusahaan',
    'perusahaan.create': 'Tambah Perusahaan',
    'perusahaan.update': 'Edit Perusahaan',
    'perusahaan.delete': 'Hapus Perusahaan',
    'user.view': 'Lihat User',
    'user.create': 'Tambah User',
    'user.update': 'Edit User',
    'user.delete': 'Hapus User',
    'user.import': 'Import User CSV',
    'user.reset-password': 'Reset Password User',
    'role.view': 'Lihat Role',
    'role.create': 'Tambah Role',
    'role.update': 'Edit Role',
    'role.delete': 'Hapus Role',
};

function permissionLabel(perm: string): string {
    return PERMISSION_LABELS[perm] ?? perm;
}

type GroupStyle = { card: string; badge: string; title: string; dot: string };
const GROUP_STYLES: Record<string, GroupStyle> = {
    customer: { card: 'border-blue-200 bg-blue-50/60 dark:border-blue-800 dark:bg-blue-950/20', badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300', title: 'text-blue-800 dark:text-blue-300', dot: 'bg-blue-500' },
    perusahaan: { card: 'border-green-200 bg-green-50/60 dark:border-green-800 dark:bg-green-950/20', badge: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300', title: 'text-green-800 dark:text-green-300', dot: 'bg-green-500' },
    user: { card: 'border-purple-200 bg-purple-50/60 dark:border-purple-800 dark:bg-purple-950/20', badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300', title: 'text-purple-800 dark:text-purple-300', dot: 'bg-purple-500' },
    role: { card: 'border-orange-200 bg-orange-50/60 dark:border-orange-800 dark:bg-orange-950/20', badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300', title: 'text-orange-800 dark:text-orange-300', dot: 'bg-orange-500' },
};
const DEFAULT_STYLE: GroupStyle = { card: 'border-muted bg-muted/40', badge: 'bg-muted text-muted-foreground', title: 'text-foreground', dot: 'bg-muted-foreground' };
const GROUP_LABELS: Record<string, string> = { customer: 'Customer', perusahaan: 'Perusahaan / Company', user: 'User', role: 'Role' };
function getGroupStyle(g: string): GroupStyle { return GROUP_STYLES[g] ?? DEFAULT_STYLE; }

interface PermissionGroupProps {
    group: string; perms: string[]; selectedPermissions: string[];
    onPermissionChange: (p: string) => void;
    onSelectAll: (g: string, c: boolean) => void;
    isAllSelected: (g: string) => boolean;
    isSomeSelected: (g: string) => boolean;
}

function PermissionGroup({ group, perms, selectedPermissions, onPermissionChange, onSelectAll, isAllSelected, isSomeSelected }: PermissionGroupProps) {
    const style = getGroupStyle(group);
    const label = GROUP_LABELS[group] ?? (group.charAt(0).toUpperCase() + group.slice(1));
    const count = perms.filter((p) => selectedPermissions.includes(p)).length;
    return (
        <div className={`rounded-xl border-2 p-5 transition-colors ${style.card}`}>
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <span className={`h-2.5 w-2.5 rounded-full ${style.dot}`} />
                    <h3 className={`text-sm font-bold tracking-wide ${style.title}`}>{label}</h3>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${style.badge}`}>{count}/{perms.length}</span>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox id={`create-select-all-${group}`} checked={isAllSelected(group)} indeterminate={isSomeSelected(group)} onCheckedChange={(c) => onSelectAll(group, !!c)} className="h-4 w-4" />
                    <Label htmlFor={`create-select-all-${group}`} className="cursor-pointer text-xs font-semibold text-muted-foreground select-none">Semua</Label>
                </div>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                {perms.map((perm) => (
                    <label key={perm} htmlFor={`create-perm-${perm}`} className="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent bg-white/60 px-3.5 py-2.5 text-xs font-medium transition-all hover:border-current hover:shadow-sm dark:bg-black/20 break-words" title={perm}>
                        <Checkbox id={`create-perm-${perm}`} checked={selectedPermissions.includes(perm)} onCheckedChange={() => onPermissionChange(perm)} className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                        <span className="leading-tight select-none">{permissionLabel(perm)}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}

// ─── DataTable ───────────────────────────────────────────────────────────────
interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

export function DataTable<TData, TValue>({ columns, data }: DataTableProps<TData, TValue>) {
    const { permissions } = usePage().props as unknown as { permissions: Record<string, string[]> };

    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [openCreate, setOpenCreate] = React.useState(false);
    const [roleName, setRoleName] = React.useState('');
    const [selectedPermissions, setSelectedPermissions] = React.useState<string[]>([]);
    const [searchTerm, setSearchTerm] = React.useState('');

    const filteredPermissions = Object.entries(permissions).reduce((acc, [group, groupPerms]) => {
        const filtered = groupPerms.filter(
            (p) => p.toLowerCase().includes(searchTerm.toLowerCase()) ||
                permissionLabel(p).toLowerCase().includes(searchTerm.toLowerCase()) ||
                group.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (GROUP_LABELS[group] ?? group).toLowerCase().includes(searchTerm.toLowerCase()),
        );
        if (filtered.length > 0) acc[group] = filtered;
        return acc;
    }, {} as Record<string, string[]>);

    const isAllSelected = (group: string) => {
        const perms = filteredPermissions[group] ?? [];
        return perms.length > 0 && perms.every((p) => selectedPermissions.includes(p));
    };
    const isSomeSelected = (group: string) => {
        const perms = filteredPermissions[group] ?? [];
        return perms.some((p) => selectedPermissions.includes(p)) && !isAllSelected(group);
    };
    const handleSelectAll = (group: string, checked: boolean) => {
        const perms = filteredPermissions[group] ?? [];
        if (checked) setSelectedPermissions((prev) => Array.from(new Set([...prev, ...perms])));
        else setSelectedPermissions((prev) => prev.filter((p) => !perms.includes(p)));
    };
    const handlePermissionChange = (perm: string) => {
        setSelectedPermissions((prev) => prev.includes(perm) ? prev.filter((p) => p !== perm) : [...prev, perm]);
    };

    const onSubmitCreate = () => {
        router.post('/role-manager', { name: roleName, permissions: selectedPermissions }, {
            onSuccess: () => {
                setOpenCreate(false);
                setRoleName('');
                setSelectedPermissions([]);
                setSearchTerm('');
            },
        });
    };

    const table = useReactTable({
        data, columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: { sorting, columnFilters, columnVisibility, rowSelection },
    });

    const totalSelected = selectedPermissions.length;
    const totalAvailable = Object.values(permissions).flat().length;

    return (
        <div>
            <div className="flex flex-col gap-2 pb-4 sm:flex-row sm:items-center">
                <Input
                    placeholder="Filter roles..."
                    value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
                    onChange={(e) => table.getColumn('name')?.setFilterValue(e.target.value)}
                    className="w-full sm:max-w-sm"
                />
                <div className="grid grid-cols-1 gap-2 sm:ml-auto sm:flex sm:items-center">
                    <DataTableViewOptions table={table} />
                    <Button className="h-9 w-full sm:w-auto" onClick={() => setOpenCreate(true)}>
                        Tambah Role
                    </Button>
                </div>
            </div>

            {/* Desktop Table */}
            <div className="hidden rounded-md border md:block">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id}>
                                {hg.headers.map((h) => (
                                    <TableHead key={h.id}>{h.isPlaceholder ? null : flexRender(h.column.columnDef.header, h.getContext())}</TableHead>
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
                                <TableCell colSpan={columns.length} className="h-24 text-center">Tidak ada data.</TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Mobile Card View */}
            <div className="space-y-3 md:hidden">
                {table.getRowModel().rows?.length ? (
                    table.getRowModel().rows.map((row) => {
                        const role = row.original as any;
                        const perms = role.permissions ?? [];
                        const displayed = perms.slice(0, 5);
                        return (
                            <div key={row.id} className="bg-card rounded-xl border p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">Role Name</p>
                                        <h3 className="mt-1 truncate text-base font-semibold leading-tight">{role.name}</h3>
                                    </div>
                                    <div className="shrink-0">
                                        {row.getVisibleCells().filter((c) => c.column.id === 'actions').map((c) => (
                                            <div key={c.id}>{flexRender(c.column.columnDef.cell, c.getContext())}</div>
                                        ))}
                                    </div>
                                </div>
                                <div className="mt-4 border-t pt-3">
                                    <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">Permissions</p>
                                    {perms.length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {displayed.map((p: any) => (
                                                <span key={p.id} className="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                                                    {permissionLabel(p.name)}
                                                </span>
                                            ))}
                                            {perms.length > 5 && (
                                                <span className="text-muted-foreground inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                                                    +{perms.length - 5} lainnya
                                                </span>
                                            )}
                                        </div>
                                    ) : <p className="text-muted-foreground mt-2 text-sm">-</p>}
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-muted-foreground rounded-lg border p-6 text-center text-sm">Tidak ada data.</div>
                )}
            </div>

            <DataTablePagination table={table} />

            {/* Create Role Modal */}
            <Dialog open={openCreate} onOpenChange={(open) => { setOpenCreate(open); if (!open) setSearchTerm(''); }}>
                <DialogContent className="flex h-[90vh] max-h-[90vh] w-[95vw] sm:w-[92vw] max-w-7xl sm:max-w-7xl xl:max-w-[1400px] flex-col gap-0 p-0">
                    <DialogHeader className="shrink-0 border-b px-6 py-4">
                        <DialogTitle className="text-lg">Tambah Role</DialogTitle>
                    </DialogHeader>

                    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto overflow-x-hidden px-6 py-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="createRoleName" className="text-sm font-semibold">Nama Role</Label>
                            <Input id="createRoleName" value={roleName} onChange={(e) => setRoleName(e.target.value)} placeholder="Masukkan nama role..." className="max-w-sm" />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-semibold">
                                    Permissions
                                    <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                                        {totalSelected}/{totalAvailable} dipilih
                                    </span>
                                </Label>
                            </div>
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input id="createSearchPermission" placeholder="Cari permission atau fitur..." value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} className="pl-9" />
                            </div>
                            <div className="space-y-3">
                                {Object.entries(filteredPermissions).length === 0 && (
                                    <p className="py-8 text-center text-sm text-muted-foreground">Tidak ada permission yang cocok.</p>
                                )}
                                {Object.entries(filteredPermissions).map(([group, groupPerms]) => (
                                    <PermissionGroup
                                        key={group} group={group} perms={groupPerms}
                                        selectedPermissions={selectedPermissions}
                                        onPermissionChange={handlePermissionChange}
                                        onSelectAll={handleSelectAll}
                                        isAllSelected={isAllSelected}
                                        isSomeSelected={isSomeSelected}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="shrink-0 border-t px-6 py-4 sm:justify-start">
                        <Button type="button" onClick={onSubmitCreate} disabled={!roleName.trim()}>Buat Role</Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">Batal</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}