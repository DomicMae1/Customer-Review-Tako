// Company/ManageCompany/table/columns.tsx
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Perusahaan } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

export const columns = (onEditClick: (perusahaan: Perusahaan) => void, onDeleteClick: (id: number) => void): ColumnDef<Perusahaan>[] => [
    {
        accessorKey: 'logo_url',
        header: 'Logo Perusahaan',
        cell: ({ row }) => {
            const logoUrl = row.original.logo_url;

            return (
                <div className="flex items-center py-2">
                    {logoUrl ? (
                        <img src={logoUrl} alt={`Logo ${row.original.nama_perusahaan}`} className="h-10 w-10 rounded-md border object-contain" />
                    ) : (
                        <Badge variant="secondary">tidak ada</Badge>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'nama_perusahaan',
        header: 'Nama Perusahaan',
        cell: ({ row }) => <div className="min-w-[150px] py-2">{row.original.nama_perusahaan}</div>,
    },
    {
        accessorKey: 'domain',
        header: 'Domain',
        cell: ({ row }) => {
            const domainName = row.original.domain?.domain;

            return (
                <div className="max-w-[260px] truncate py-2">
                    {domainName ? (
                        <span className="max-w-[260px] truncate text-sm font-medium text-foreground">
                            {domainName}
                        </span>
                    ) : (
                        <Badge variant="secondary">tidak ada</Badge>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'is_ppjk',
        header: 'PPJK',
        cell: ({ row }) => (
            row.original.is_ppjk ? <Badge className="py-1">PPJK</Badge> : <Badge variant="secondary">Non-PPJK</Badge>
        ),
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const perusahaan = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onEditClick(perusahaan)}>Edit</DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onDeleteClick(perusahaan.id)}>Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
