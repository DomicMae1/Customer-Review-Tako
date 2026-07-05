import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/app-layout';
import { Role, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { columns } from './table/columns';
import { DataTable } from './table/data-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Role',
        href: '/role-manager',
    },
];

// ─── Label Indonesia per permission ────────────────────────────────────────
const PERMISSION_LABELS: Record<string, string> = {
    // Customer
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
    // Perusahaan
    'perusahaan.view': 'Lihat Perusahaan',
    'perusahaan.create': 'Tambah Perusahaan',
    'perusahaan.update': 'Edit Perusahaan',
    'perusahaan.delete': 'Hapus Perusahaan',
    // User
    'user.view': 'Lihat User',
    'user.create': 'Tambah User',
    'user.update': 'Edit User',
    'user.delete': 'Hapus User',
    'user.import': 'Import User CSV',
    'user.reset-password': 'Reset Password User',
    // Role
    'role.view': 'Lihat Role',
    'role.create': 'Tambah Role',
    'role.update': 'Edit Role',
    'role.delete': 'Hapus Role',
};

function permissionLabel(perm: string): string {
    return PERMISSION_LABELS[perm] ?? perm;
}

// ─── Warna per grup fitur ───────────────────────────────────────────────────
type GroupStyle = {
    card: string;
    badge: string;
    title: string;
    dot: string;
};

const GROUP_STYLES: Record<string, GroupStyle> = {
    customer: {
        card: 'border-blue-200 bg-blue-50/60 dark:border-blue-800 dark:bg-blue-950/20',
        badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        title: 'text-blue-800 dark:text-blue-300',
        dot: 'bg-blue-500',
    },
    perusahaan: {
        card: 'border-green-200 bg-green-50/60 dark:border-green-800 dark:bg-green-950/20',
        badge: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        title: 'text-green-800 dark:text-green-300',
        dot: 'bg-green-500',
    },
    user: {
        card: 'border-purple-200 bg-purple-50/60 dark:border-purple-800 dark:bg-purple-950/20',
        badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
        title: 'text-purple-800 dark:text-purple-300',
        dot: 'bg-purple-500',
    },
    role: {
        card: 'border-orange-200 bg-orange-50/60 dark:border-orange-800 dark:bg-orange-950/20',
        badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
        title: 'text-orange-800 dark:text-orange-300',
        dot: 'bg-orange-500',
    },
};

const DEFAULT_STYLE: GroupStyle = {
    card: 'border-muted bg-muted/40',
    badge: 'bg-muted text-muted-foreground',
    title: 'text-foreground',
    dot: 'bg-muted-foreground',
};

const GROUP_LABELS: Record<string, string> = {
    customer: 'Customer',
    perusahaan: 'Perusahaan / Company',
    user: 'User',
    role: 'Role',
};

function getGroupStyle(group: string): GroupStyle {
    return GROUP_STYLES[group] ?? DEFAULT_STYLE;
}

// ─── Komponen Permission Group ──────────────────────────────────────────────
interface PermissionGroupProps {
    group: string;
    perms: string[];
    selectedPermissions: string[];
    onPermissionChange: (perm: string) => void;
    onSelectAll: (group: string, checked: boolean) => void;
    isAllSelected: (group: string) => boolean;
    isSomeSelected: (group: string) => boolean;
}

function PermissionGroup({
    group,
    perms,
    selectedPermissions,
    onPermissionChange,
    onSelectAll,
    isAllSelected,
    isSomeSelected,
}: PermissionGroupProps) {
    const style = getGroupStyle(group);
    const label = GROUP_LABELS[group] ?? group.charAt(0).toUpperCase() + group.slice(1);
    const count = perms.filter((p) => selectedPermissions.includes(p)).length;

    return (
        <div className={`rounded-xl border-2 p-5 transition-colors ${style.card}`}>
            {/* Header grup */}
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <span className={`h-2.5 w-2.5 rounded-full ${style.dot}`} />
                    <h3 className={`text-sm font-bold tracking-wide ${style.title}`}>{label}</h3>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${style.badge}`}>
                        {count}/{perms.length}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`select-all-${group}`}
                        checked={isAllSelected(group)}
                        indeterminate={isSomeSelected(group)}
                        onCheckedChange={(checked) => onSelectAll(group, !!checked)}
                        className="h-4 w-4"
                    />
                    <Label htmlFor={`select-all-${group}`} className="cursor-pointer text-xs font-semibold text-muted-foreground select-none">
                        Semua
                    </Label>
                </div>
            </div>

            {/* Permission checkboxes — 1 kolom di mobile, 2-3 di tablet, 4-5 di desktop */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                {perms.map((perm) => (
                    <label
                        key={perm}
                        htmlFor={`perm-${perm}`}
                        className="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent bg-white/60 px-3.5 py-2.5 text-xs font-medium transition-all hover:border-current hover:shadow-sm dark:bg-black/20 break-words"
                        title={perm}
                    >
                        <Checkbox
                            id={`perm-${perm}`}
                            checked={selectedPermissions.includes(perm)}
                            onCheckedChange={() => onPermissionChange(perm)}
                            className="h-3.5 w-3.5 shrink-0 mt-0.5"
                        />
                        <span className="leading-tight select-none">{permissionLabel(perm)}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}

// ─── Main Page ──────────────────────────────────────────────────────────────
export default function ManageRoles() {
    const { roles, permissions, flash } = usePage().props as unknown as {
        roles: Role[];
        permissions: { [key: string]: string[] };
        flash: { success?: string; error?: string };
    };

    const [openDelete, setOpenDelete] = useState(false);
    const [openForm, setOpenForm] = useState(false);
    const [roleIdToDelete, setRoleIdToDelete] = useState<number | null>(null);
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);
    const [roleName, setRoleName] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Filter permission berdasarkan search
    const filteredPermissions = Object.entries(permissions).reduce(
        (acc, [group, groupPerms]) => {
            const filtered = groupPerms.filter(
                (perm) =>
                    perm.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    permissionLabel(perm).toLowerCase().includes(searchTerm.toLowerCase()) ||
                    group.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    (GROUP_LABELS[group] ?? group).toLowerCase().includes(searchTerm.toLowerCase()),
            );
            if (filtered.length > 0) acc[group] = filtered;
            return acc;
        },
        {} as Record<string, string[]>,
    );

    const isAllSelected = (group: string) => {
        const groupPerms = filteredPermissions[group] ?? [];
        return groupPerms.length > 0 && groupPerms.every((p) => selectedPermissions.includes(p));
    };

    const isSomeSelected = (group: string) => {
        const groupPerms = filteredPermissions[group] ?? [];
        return groupPerms.some((p) => selectedPermissions.includes(p)) && !isAllSelected(group);
    };

    const handleSelectAll = (group: string, checked: boolean) => {
        const groupPerms = filteredPermissions[group] ?? [];
        if (checked) {
            setSelectedPermissions((prev) => Array.from(new Set([...prev, ...groupPerms])));
        } else {
            setSelectedPermissions((prev) => prev.filter((p) => !groupPerms.includes(p)));
        }
    };

    const handlePermissionChange = (perm: string) => {
        setSelectedPermissions((prev) => (prev.includes(perm) ? prev.filter((p) => p !== perm) : [...prev, perm]));
    };

    const onDeleteClick = (id: number) => {
        setRoleIdToDelete(id);
        setOpenDelete(true);
    };

    const onEditClick = (role: Role) => {
        setSelectedRole(role);
        setRoleName(role.name);
        setSelectedPermissions(role.permissions.map((p) => p.name));
        setSearchTerm('');
        setOpenForm(true);
    };

    const onConfirmDelete = () => {
        if (roleIdToDelete) {
            router.delete(`/role-manager/${roleIdToDelete}`, {
                onSuccess: () => {
                    setOpenDelete(false);
                    setRoleIdToDelete(null);
                },
            });
        }
    };

    const onSubmit = () => {
        const data = { name: roleName, permissions: selectedPermissions };

        if (selectedRole) {
            router.put(`/role-manager/${selectedRole.id}`, data, {
                onSuccess: () => {
                    setOpenForm(false);
                    setSelectedRole(null);
                    setRoleName('');
                    setSelectedPermissions([]);
                    setSearchTerm('');
                },
            });
        } else {
            router.post('/role-manager', data, {
                onSuccess: () => {
                    setOpenForm(false);
                    setRoleName('');
                    setSelectedPermissions([]);
                    setSearchTerm('');
                },
            });
        }
    };

    const totalSelected = selectedPermissions.length;
    const totalAvailable = Object.values(permissions).flat().length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Roles" />
            <div className="p-4">
                <DataTable columns={columns(onEditClick, onDeleteClick)} data={roles} />
            </div>

            {/* ── Delete dialog ── */}
            <Dialog open={openDelete} onOpenChange={setOpenDelete}>
                <DialogContent className="max-w-[90vw] sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Role</DialogTitle>
                        <div className="mt-2 text-sm text-muted-foreground">Role ini akan dihapus permanen. Apakah Anda yakin?</div>
                    </DialogHeader>
                    <DialogFooter className="sm:justify-start">
                        <Button type="button" variant="destructive" onClick={onConfirmDelete}>
                            Hapus
                        </Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Create / Edit dialog ── */}
            <Dialog
                open={openForm}
                onOpenChange={(open) => {
                    setOpenForm(open);
                    if (!open) {
                        setSearchTerm('');
                        setSelectedRole(null);
                        setRoleName('');
                        setSelectedPermissions([]);
                    }
                }}
            >
                {/* Modal lebih lebar: full di mobile, 5xl di desktop */}
                <DialogContent className="flex h-[90vh] max-h-[90vh] w-[95vw] sm:w-[92vw] max-w-7xl sm:max-w-7xl xl:max-w-[1400px] flex-col gap-0 p-0">
                    {/* Header */}
                    <DialogHeader className="shrink-0 border-b px-6 py-4">
                        <DialogTitle className="text-lg">{selectedRole ? 'Edit Role' : 'Tambah Role'}</DialogTitle>
                    </DialogHeader>

                    {/* Body — scrollable */}
                    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto overflow-x-hidden px-6 py-4">
                        {/* Role name input */}
                        <div className="space-y-1.5">
                            <Label htmlFor="roleName" className="text-sm font-semibold">
                                Nama Role
                            </Label>
                            <Input
                                id="roleName"
                                value={roleName}
                                onChange={(e) => setRoleName(e.target.value)}
                                placeholder="Masukkan nama role..."
                                className="max-w-sm"
                            />
                        </div>

                        {/* Permissions */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-semibold">
                                    Permissions
                                    <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                                        {totalSelected}/{totalAvailable} dipilih
                                    </span>
                                </Label>
                            </div>

                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="searchPermission"
                                    placeholder="Cari permission atau fitur..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-9"
                                />
                            </div>

                            {/* Grup permission */}
                            <div className="space-y-3">
                                {Object.entries(filteredPermissions).length === 0 && (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        Tidak ada permission yang cocok dengan pencarian.
                                    </p>
                                )}
                                {Object.entries(filteredPermissions).map(([group, groupPerms]) => (
                                    <PermissionGroup
                                        key={group}
                                        group={group}
                                        perms={groupPerms}
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

                    {/* Footer — sticky di bawah */}
                    <DialogFooter className="shrink-0 border-t px-6 py-4 sm:justify-start">
                        <Button type="button" onClick={onSubmit} disabled={!roleName.trim()}>
                            {selectedRole ? 'Simpan Perubahan' : 'Buat Role'}
                        </Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}