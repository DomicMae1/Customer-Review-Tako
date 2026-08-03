// Role/ManageRoles/table/columns.tsx
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Role } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal, ShieldCheck } from 'lucide-react';

// ─── Label & warna (sama dengan page.tsx) ───────────────────────────────────
const PERMISSION_LABELS: Record<string, string> = {
    'customer.view': 'Lihat Customer',
    'customer.bank.view': 'Lihat Bank Customer',
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
    'supplier.view': 'Lihat Supplier',
    'supplier.bank.view': 'Lihat Bank Supplier',
    'supplier.create': 'Tambah Supplier',
    'supplier.update': 'Edit Supplier',
    'supplier.delete': 'Hapus Supplier',
    'supplier.pdf': 'Download PDF',
    'supplier.import': 'Import CSV',
    'supplier.link.create': 'Buat Link Publik',
    'supplier.approve.manager': 'Approve (Manager)',
    'supplier.approve.direktur': 'Approve (Direktur)',
    'supplier.approve.lawyer': 'Approve (Lawyer)',
    'supplier.approve.auditor': 'Review (Auditor)',
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

type GroupInfo = { label: string; badge: string; dot: string };
const GROUP_INFO: Record<string, GroupInfo> = {
    customer:   { label: 'Customer',   badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',     dot: 'bg-blue-500' },
    supplier:   { label: 'Supplier',   badge: 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-300',     dot: 'bg-teal-500' },
    perusahaan: { label: 'Perusahaan', badge: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300', dot: 'bg-green-500' },
    user:       { label: 'User',       badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300', dot: 'bg-purple-500' },
    role:       { label: 'Role',       badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300', dot: 'bg-orange-500' },
};
const DEFAULT_INFO: GroupInfo = { label: 'Lainnya', badge: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground' };

function permLabel(name: string): string {
    return PERMISSION_LABELS[name] ?? name;
}

function groupOf(permName: string): string {
    return permName.includes('.') ? permName.split('.')[0] : 'other';
}

// ─── Columns ────────────────────────────────────────────────────────────────
export const columns = (onEditClick: (role: Role) => void, onDeleteClick: (id: number) => void): ColumnDef<Role>[] => [
    {
        accessorKey: 'name',
        header: 'Nama Role',
        cell: ({ row }) => (
            <div className="flex min-w-[120px] items-center gap-2 py-2">
                <ShieldCheck className="h-4 w-4 shrink-0 text-muted-foreground" />
                <span className="font-semibold">{row.original.name}</span>
            </div>
        ),
    },
    {
        id: 'permission_count',
        header: 'Jumlah',
        cell: ({ row }) => {
            const count = row.original.permissions.length;
            return (
                <div className="flex items-center">
                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums ${
                        count === 0 ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary'
                    }`}>
                        {count}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: 'permissions',
        header: 'Permissions',
        cell: ({ row }) => {
            const perms = row.original.permissions;

            if (perms.length === 0) {
                return <span className="text-xs text-muted-foreground italic">Tidak ada permission</span>;
            }

            // Grup permission berdasarkan prefix
            const grouped: Record<string, string[]> = {};
            perms.forEach((p) => {
                const g = groupOf(p.name);
                if (!grouped[g]) grouped[g] = [];
                grouped[g].push(p.name);
            });

            // Hanya tampilkan dot-notation groups
            const modernGroups = Object.entries(grouped).filter(([g]) => g !== 'other');
            const legacyCount = (grouped['other'] ?? []).length;

            // Max berapa badge group yang ditampilkan langsung
            const MAX_GROUPS_INLINE = 3;
            const inlineGroups = modernGroups.slice(0, MAX_GROUPS_INLINE);
            const hiddenGroups = modernGroups.slice(MAX_GROUPS_INLINE);
            const hiddenCount = hiddenGroups.reduce((n, [, ps]) => n + ps.length, 0) + legacyCount;

            return (
                <div className="flex flex-wrap items-center gap-1.5 py-1">
                    {inlineGroups.map(([group, groupPerms]) => {
                        const info = GROUP_INFO[group] ?? DEFAULT_INFO;
                        return (
                            <TooltipProvider key={group} delayDuration={100}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className={`inline-flex cursor-default items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${info.badge}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${info.dot}`} />
                                            {info.label}
                                            <span className="ml-0.5 opacity-70">({groupPerms.length})</span>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-xs border bg-popover p-3">
                                        <p className="mb-2 text-xs font-bold">{info.label}</p>
                                        <div className="flex flex-wrap gap-1">
                                            {groupPerms.map((pn) => (
                                                <span key={pn} className={`rounded-full px-2 py-0.5 text-xs ${info.badge}`}>
                                                    {permLabel(pn)}
                                                </span>
                                            ))}
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        );
                    })}

                    {hiddenCount > 0 && (
                        <TooltipProvider delayDuration={100}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span className="cursor-default rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                        +{hiddenCount} lainnya
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-sm border bg-popover p-3">
                                    <div className="space-y-2">
                                        {hiddenGroups.map(([group, groupPerms]) => {
                                            const info = GROUP_INFO[group] ?? DEFAULT_INFO;
                                            return (
                                                <div key={group}>
                                                    <p className="mb-1 text-xs font-bold">{info.label}</p>
                                                    <div className="flex flex-wrap gap-1">
                                                        {groupPerms.map((pn) => (
                                                            <span key={pn} className={`rounded-full px-2 py-0.5 text-xs ${info.badge}`}>
                                                                {permLabel(pn)}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        {legacyCount > 0 && (
                                            <p className="text-xs text-muted-foreground">{legacyCount} legacy permission</p>
                                        )}
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
            );
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const role = row.original;
            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onEditClick(role)}>Edit</DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onDeleteClick(role.id)} className="text-destructive focus:text-destructive">
                            Hapus
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];