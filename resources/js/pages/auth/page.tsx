/* eslint-disable @typescript-eslint/no-explicit-any */
// Users/ManageUsers.tsx
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Role, User, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import Swal from 'sweetalert2';
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
    const [editUid, setEditUid] = useState('');
    const [editNIK, setEditNIK] = useState('');
    const [editRole, setEditRole] = useState<string>('');
    const [editCompany, setEditCompany] = useState<string>('');

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

    const onlyNumber = (value: string) => value.replace(/\D/g, '');

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

        const uidOnlyNumber = onlyNumber(editUid);
        const nikOnlyNumber = onlyNumber(editNIK);

        if (uidOnlyNumber.length !== 8) {
            toast.error('UID harus 8 digit angka.');
            return;
        }

        if (nikOnlyNumber.length !== 16) {
            toast.error('NIK harus 16 digit angka.');
            return;
        }

        const data: any = {
            name: editName,
            uid: uidOnlyNumber,
            NIK: nikOnlyNumber,
            email: editEmail,
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
                    setEditRole('');
                    setEditCompany('');
                    toast.success('User updated successfully!');
                },
                onError: (errors) => {
                    console.error('❌ Error saat mengedit user:', errors);

                    const firstError = Object.values(errors)[0];
                    toast.error(String(firstError ?? 'Failed to update user.'));
                },
            });
        }
    };

    const handleResetPassword = async (id: number) => {
        const result = await Swal.fire({
            title: 'Reset Password?',
            text: 'Password user akan dikembalikan ke default 6 angka terakhir NIK.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, reset',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
        });

        if (!result.isConfirmed) return;

        router.post(
            route('users.reset-password', id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Password berhasil direset.');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(String(firstError ?? 'Gagal reset password.'));
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Users" />
            <div className="p-4">
                <DataTable columns={columns(onDeleteClick, onEditClick, handleResetPassword)} data={users} />
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
                            <Label htmlFor="edit_uid">UID</Label>
                            <Input
                                id="edit_uid"
                                value={editUid}
                                inputMode="numeric"
                                maxLength={8}
                                onChange={(e) => setEditUid(e.target.value.replace(/\D/g, '').slice(0, 8))}
                                placeholder="Masukkan UID 8 digit"
                            />
                            <p className="text-muted-foreground mt-1 text-xs">UID wajib 8 digit angka.</p>
                        </div>

                        <div>
                            <Label htmlFor="edit_NIK">NIK</Label>
                            <Input
                                id="edit_NIK"
                                value={editNIK}
                                inputMode="numeric"
                                maxLength={16}
                                onChange={(e) => setEditNIK(e.target.value.replace(/\D/g, '').slice(0, 16))}
                                placeholder="Masukkan NIK 16 digit"
                            />
                            <p className="text-muted-foreground mt-1 text-xs">NIK wajib 16 digit angka.</p>
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
