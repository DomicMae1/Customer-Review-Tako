// Users/table/columns.tsx
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { User } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

export const columns = (
    onDeleteClick: (id: number) => void,
    onEditClick: (id: number) => void,
    onResetPasswordClick: (id: number) => void,
): ColumnDef<User>[] => [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => <div className="py-2">{row.original.name}</div>,
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => <div className="py-2">{row.original.email}</div>,
    },
    {
        accessorKey: 'uid',
        header: 'UID',
        cell: ({ row }) => <div className="py-2">{row.original.uid}</div>,
    },
    {
        accessorKey: 'roles',
        header: 'Roles',
        cell: ({ row }) => {
            const roles = row.original.roles;
            return <div className="py-2">{roles.map((role) => role.name).join(', ')}</div>;
        },
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            const user = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => onEditClick(user.id)}>Edit</DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onResetPasswordClick(user.id)}>Reset Password</DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onDeleteClick(user.id)}>Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
