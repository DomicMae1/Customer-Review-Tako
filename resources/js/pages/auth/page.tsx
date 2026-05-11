// Users/ManageUsers.tsx
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Role, User, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { columns } from './table/columns';
import { DataTable } from './table/data-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Users',
        href: '/users',
    },
];

interface Perusahaan {
    id: number;
    nama_perusahaan: string;
}

export default function ManageUsers() {
    const { users, roles, companies } = usePage().props as unknown as {
        users: User[];
        roles: Role[];
        companies: Perusahaan[]; // Tambahkan ini
    };
    const [openDelete, setOpenDelete] = useState(false);
    const [userIdToDelete, setUserIdToDelete] = useState<number | null>(null);
    const [openEdit, setOpenEdit] = useState(false);
    const [userIdToEdit, setUserIdToEdit] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [editEmail, setEditEmail] = useState('');
    const [editPassword, setEditPassword] = useState('');
    const [editUid, setEditUid] = useState('');
    const [editNIK, setEditNIK] = useState('');
    const [editRole, setEditRole] = useState<string>('');
    const [editCompany, setEditCompany] = useState<string>('');

    const [showEditPassword, setShowEditPassword] = useState(false);

    const selectedEditRoleName = roles.find((role) => String(role.id) === editRole)?.name;

    const userToDelete = users.find((u) => u.id === userIdToDelete);

    const onEditClick = (id: number) => {
        const user = users.find((u) => u.id === id);

        if (user) {
            setUserIdToEdit(id);
            setEditName(user.name);
            setEditUid(user.uid ?? '');
            setEditNIK(user.NIK ?? '');
            setEditEmail(user.email);
            setEditPassword('');
            setEditRole(user.roles && user.roles.length > 0 ? String(user.roles[0].id) : '');
            setEditCompany(user.id_perusahaan ? String(user.id_perusahaan) : '');
            setOpenEdit(true);
        }
    };

    const onDeleteClick = (id: number) => {
        setUserIdToDelete(id);
        setOpenDelete(true);
    };

    const onConfirmDelete = () => {
        if (userIdToDelete !== null) {
            router.delete(`/users/${userIdToDelete}`, {
                onSuccess: () => {
                    setOpenDelete(false);
                    setUserIdToDelete(null);
                    toast.success('User deleted successfully!');
                },
                onError: (errors) => {
                    console.error('❌ Error saat menghapus user:', errors);
                    toast.error('Failed to delete user.');
                },
            });
        }
    };

    const onConfirmEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!editName || !editEmail || !editRole) {
            toast.error('All fields are required.');
            return;
        }

        if (selectedEditRoleName === 'user' && !editCompany) {
            toast.error('Please select a company for User role.');
            return;
        }

        const data = {
            name: editName,
            uid: editUid || null,
            NIK: editNIK || null,
            email: editEmail,
            password: editPassword || null,
            role: editRole,
            id_perusahaan: selectedEditRoleName === 'user' ? editCompany : null,
        };

        if (userIdToEdit !== null) {
            router.put(`/users/${userIdToEdit}`, data, {
                onSuccess: () => {
                    setOpenEdit(false);
                    setUserIdToEdit(null);
                    setEditName('');
                    setEditUid('');
                    setEditNIK('');
                    setEditEmail('');
                    setEditPassword('');
                    setEditRole('');
                    setEditCompany('');
                    toast.success('User updated successfully!');
                },
                onError: (errors) => {
                    console.error('❌ Error saat mengedit user:', errors);
                    if (errors.email) {
                        toast.error('Email error: ' + errors.email);
                    } else if (errors.role) {
                        toast.error('Role error: ' + errors.role);
                    } else {
                        toast.error('Failed to update user.');
                    }
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Users" />
            <div className="p-4">
                <DataTable columns={columns(onDeleteClick, onEditClick)} data={users} />
            </div>

            <Dialog open={openDelete} onOpenChange={setOpenDelete}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Data</DialogTitle>
                        <div className="mt-2">
                            Data <span className="font-bold text-white">{userToDelete?.email ?? 'Tidak ditemukan'}</span> akan dihapus. Apakah Anda
                            yakin?
                        </div>
                    </DialogHeader>
                    <DialogFooter className="sm:justify-start">
                        <Button type="button" variant="destructive" onClick={onConfirmDelete}>
                            Hapus
                        </Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Close
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={openEdit}
                onOpenChange={(open) => {
                    setOpenEdit(open);
                    if (!open) {
                        setUserIdToEdit(null);
                        setEditName('');
                        setEditUid('');
                        setEditNIK('');
                        setEditEmail('');
                        setEditRole('');
                        setEditCompany('');
                        setShowEditPassword(false);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit User</DialogTitle>
                        <DialogDescription>Update the details of the user.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={onConfirmEdit} className="space-y-4">
                        <div>
                            <Label htmlFor="edit_name">Name</Label>
                            <Input id="edit_name" value={editName} onChange={(e) => setEditName(e.target.value)} placeholder="Enter name" />
                        </div>
                        <div>
                            <Label htmlFor="edit_email">Email</Label>
                            <Input
                                id="edit_email"
                                type="email"
                                value={editEmail}
                                onChange={(e) => setEditEmail(e.target.value)}
                                placeholder="Enter email"
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit_password">Password Baru</Label>
                            <div className="relative">
                                <Input
                                    id="edit_password"
                                    type={showEditPassword ? 'text' : 'password'}
                                    value={editPassword}
                                    onChange={(e) => setEditPassword(e.target.value)}
                                    placeholder="Kosongkan jika tidak ingin mengubah password"
                                    className="pr-10"
                                />

                                <button
                                    type="button"
                                    onClick={() => setShowEditPassword((prev) => !prev)}
                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                    tabIndex={-1}
                                >
                                    {showEditPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="edit_uid">UID</Label>
                            <Input id="edit_uid" value={editUid} onChange={(e) => setEditUid(e.target.value)} placeholder="Enter UID" />
                        </div>

                        <div>
                            <Label htmlFor="edit_NIK">NIK</Label>
                            <Input id="edit_NIK" value={editNIK} onChange={(e) => setEditNIK(e.target.value)} placeholder="Enter NIK" />
                        </div>
                        <div>
                            <Label htmlFor="edit_role">Role</Label>
                            <Select onValueChange={setEditRole} value={editRole}>
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

                        {selectedEditRoleName === 'user' && (
                            <div>
                                <Label htmlFor="edit_company">Perusahaan</Label>
                                <Select onValueChange={setEditCompany} value={editCompany}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a company" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companies.map((comp) => (
                                            <SelectItem key={comp.id} value={String(comp.id)}>
                                                {comp.nama_perusahaan}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <DialogFooter className="sm:justify-start">
                            <Button type="submit">Save</Button>
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
