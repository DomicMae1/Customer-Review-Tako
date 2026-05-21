// Role/ManageRoles/table/columns.tsx
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Role } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';

export const columns = (onEditClick: (role: Role) => void, onDeleteClick: (id: number) => void): ColumnDef<Role>[] => [
    {
        accessorKey: 'name',
        header: 'Role Name',
        cell: ({ row }) => <div className="min-w-[120px] py-2 font-medium">{row.original.name}</div>,
    },
    {
        accessorKey: 'permissions',
        header: 'Permissions',
        cell: ({ row }) => {
            const permissions = row.original.permissions;
            const maxPermissionsToShow = 4;

            const displayedPermissions = permissions.slice(0, maxPermissionsToShow);
            const hasMorePermissions = permissions.length > maxPermissionsToShow;

            return (
                <div className="flex max-w-[800px] items-center gap-2 2xl:max-w-[1440px]">
                    {displayedPermissions.map((perm) => (
                        <Badge key={perm.id} className="text-xs" variant="outline">
                            {perm.name}
                        </Badge>
                    ))}
                    {hasMorePermissions && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger>
                                    <span className="text-muted-foreground cursor-pointer text-xs">...</span>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-2xl border bg-zinc-950 p-4 2xl:max-w-7xl">
                                    <div className="flex flex-wrap gap-2">
                                        {permissions.map((perm) => (
                                            <Badge key={perm.id} className="text-xs" variant="secondary">
                                                {perm.name}
                                            </Badge>
                                        ))}
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
                        <DropdownMenuItem onClick={() => onDeleteClick(role.id)}>Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
