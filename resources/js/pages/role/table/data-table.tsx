import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
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

export function DataTable<TData, TValue>({ columns, data }: DataTableProps<TData, TValue>) {
    const { permissions } = usePage().props as unknown as {
        permissions: { [key: string]: string[] };
    };

    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});

    const [openCreate, setOpenCreate] = React.useState(false);
    const [roleName, setRoleName] = React.useState('');
    const [selectedPermissions, setSelectedPermissions] = React.useState<string[]>([]);

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

    const handlePermissionChange = (permission: string) => {
        if (selectedPermissions.includes(permission)) {
            setSelectedPermissions(selectedPermissions.filter((perm) => perm !== permission));
        } else {
            setSelectedPermissions([...selectedPermissions, permission]);
        }
    };

    const onSubmitCreate = () => {
        const data = {
            name: roleName,
            permissions: selectedPermissions,
        };

        router.post('/role-manager', data, {
            onSuccess: () => {
                setOpenCreate(false);
                setRoleName('');
                setSelectedPermissions([]);
            },
            onError: (errors) => {
                console.error('❌ Error saat menambah role:', errors);
            },
        });
    };

    return (
        <div>
            <div className="flex flex-col gap-2 pb-4 sm:flex-row sm:items-center">
                <Input
                    placeholder="Filter roles..."
                    value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
                    onChange={(event) => table.getColumn('name')?.setFilterValue(event.target.value)}
                    className="w-full sm:max-w-sm"
                />

                <div className="grid grid-cols-1 gap-2 sm:ml-auto sm:flex sm:items-center">
                    <DataTableViewOptions table={table} />

                    <Button className="h-9 w-full sm:w-auto" onClick={() => setOpenCreate(true)}>
                        Add Role
                    </Button>
                </div>
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
                        const role = row.original as any;
                        const permissions = role.permissions ?? [];
                        const displayedPermissions = permissions.slice(0, 5);
                        const hasMorePermissions = permissions.length > 5;

                        return (
                            <div key={row.id} className="bg-card rounded-xl border p-4 shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Role Name</p>
                                        <h3 className="mt-1 truncate text-base leading-tight font-semibold">{role.name}</h3>
                                    </div>

                                    <div className="shrink-0">
                                        {row
                                            .getVisibleCells()
                                            .filter((cell) => cell.column.id === 'actions')
                                            .map((cell) => (
                                                <div key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</div>
                                            ))}
                                    </div>
                                </div>

                                <div className="mt-4 border-t pt-3">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Permissions</p>

                                    {permissions.length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {displayedPermissions.map((permission: any) => (
                                                <span
                                                    key={permission.id}
                                                    className="inline-flex max-w-full items-center rounded-md border px-2 py-1 text-xs font-medium"
                                                >
                                                    <span className="max-w-[240px] truncate">{permission.name}</span>
                                                </span>
                                            ))}

                                            {hasMorePermissions && (
                                                <span className="text-muted-foreground inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                                                    +{permissions.length - displayedPermissions.length} more
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground mt-2 text-sm">-</p>
                                    )}
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-muted-foreground rounded-lg border p-6 text-center text-sm">No results.</div>
                )}
            </div>

            <DataTablePagination table={table} />

            <Dialog open={openCreate} onOpenChange={setOpenCreate}>
                <DialogContent className="max-h-[90vh] w-[92vw] overflow-y-auto rounded-lg sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Add Role</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="roleName">Role Name</Label>
                            <Input id="roleName" value={roleName} onChange={(e) => setRoleName(e.target.value)} placeholder="Enter role name" />
                        </div>
                        <div>
                            <Label>Permissions</Label>
                            <ScrollArea className="w-full rounded-md border">
                                <div className="max-h-80 space-y-8 p-4 2xl:max-h-96">
                                    {Object.entries(permissions).map(([model, modelPermissions]) => (
                                        <div key={model}>
                                            <h3 className="font-semibold capitalize">{model.replace(/-/g, ' ')}</h3>
                                            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                {modelPermissions.map((permission) => {
                                                    const action = permission.split('-')[0];
                                                    return (
                                                        <div key={permission} className="flex items-center">
                                                            <Checkbox
                                                                id={permission}
                                                                checked={selectedPermissions.includes(permission)}
                                                                onCheckedChange={() => handlePermissionChange(permission)}
                                                                className="mr-2"
                                                            />
                                                            <label htmlFor={permission} className="cursor-pointer">
                                                                {action}
                                                            </label>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        </div>
                    </div>
                    <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-start">
                        <Button type="button" onClick={onSubmitCreate} className="w-full sm:w-auto">
                            Create
                        </Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary" className="w-full sm:w-auto">
                                Cancel
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
