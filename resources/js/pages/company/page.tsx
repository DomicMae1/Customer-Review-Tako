/* eslint-disable @typescript-eslint/no-explicit-any */
import { ResettableDropzoneImage } from '@/components/ResettableDropzoneImage';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { columns } from './table/columns';
import { DataTable } from './table/data-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Company',
        href: '/perusahaan',
    },
];

interface FormState {
    nama_perusahaan: string;
    domain: string;
    id_User_1: string;
    id_User_2: string;
    id_User_3: string;
    notify_1: string;
    notify_2: string;
    logo_url: string;
}

export default function ManageCompany() {
    const { props } = usePage();
    const { companies, flash } = props as unknown as {
        companies: any[];
        flash: { success?: string; error?: string };
        users: any[];
    };

    const [companyLogoFile, setCompanyLogoFile] = useState<File | null>(null);

    const initialFormState: FormState = {
        nama_perusahaan: '',
        domain: '',
        id_User_1: '',
        id_User_2: '',
        id_User_3: '',
        notify_1: '',
        notify_2: '',
        logo_url: '',
    };

    const [form, setForm] = useState<FormState>(initialFormState);
    const [openForm, setOpenForm] = useState(false);
    const [openDelete, setOpenDelete] = useState(false);
    const [selectedCompany, setSelectedCompany] = useState<any | null>(null);
    const [companyIdToDelete, setCompanyIdToDelete] = useState<number | null>(null);

    const userRoles = [
        { key: 'id_User_1', label: 'Manager' },
        { key: 'id_User_2', label: 'Direktur' },
        { key: 'id_User_3', label: 'Lawyer' },
    ];

    const handleUserChange = (field: keyof FormState, value: string) => {
        setForm((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash]);

    const handleInputChange = (e: ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;

        setForm((prevForm) => ({
            ...prevForm,
            [name]: value,
        }));
    };

    const resetFormAndClose = () => {
        setForm(initialFormState);
        setCompanyLogoFile(null);
        setSelectedCompany(null);
        setOpenForm(false);
    };

    const onEditClick = (company: any) => {
        setSelectedCompany(company);
        setCompanyLogoFile(null);

        const manager = company.users?.find((u: any) => u.pivot.role === 'manager');
        const direktur = company.users?.find((u: any) => u.pivot.role === 'direktur');
        const lawyer = company.users?.find((u: any) => u.pivot.role === 'lawyer');

        setForm({
            nama_perusahaan: company.nama_perusahaan || '',
            domain: company.domain?.domain || (company.logo_url ? new URL(company.logo_url).hostname : ''),
            id_User_1: manager ? String(manager.id) : '',
            id_User_2: direktur ? String(direktur.id) : '',
            id_User_3: lawyer ? String(lawyer.id) : '',
            notify_1: company.notify_1 || '',
            notify_2: company.notify_2 || '',
            logo_url: company.logo_url || '',
        });

        setOpenForm(true);
    };

    const onDeleteClick = (id: number) => {
        setCompanyIdToDelete(id);
        setOpenDelete(true);
    };

    const onConfirmDelete = () => {
        if (companyIdToDelete) {
            router.delete(`/perusahaan/${companyIdToDelete}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setOpenDelete(false);
                    setCompanyIdToDelete(null);
                    toast.success('Perusahaan berhasil dihapus');

                    router.reload({ only: ['companies'] });
                },
                onError: () => {
                    toast.error('Gagal menghapus perusahaan');
                },
            });
        }
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        const formData = new FormData();

        Object.entries(form).forEach(([key, value]) => {
            if (value !== null) formData.append(key, value);
        });

        if (companyLogoFile) {
            formData.append('company_logo', companyLogoFile);
        }

        if (selectedCompany) {
            formData.append('_method', 'PUT');

            router.post(`/perusahaan/${selectedCompany.id}`, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Perusahaan berhasil diperbarui');
                    resetFormAndClose();
                    router.reload({ only: ['companies'] });
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error('Gagal memperbarui perusahaan');
                },
            });
        } else {
            router.post('/perusahaan', formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Perusahaan berhasil ditambahkan');
                    resetFormAndClose();
                    router.reload({ only: ['companies'] });
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error('Gagal menambah perusahaan');
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Companies" />

            <div className="w-full overflow-x-hidden p-3 sm:p-4">
                <DataTable columns={columns(onEditClick, onDeleteClick)} data={companies} />
            </div>

            <Dialog open={openDelete} onOpenChange={setOpenDelete}>
                <DialogContent className="max-h-[90vh] w-[92vw] overflow-y-auto rounded-lg sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Perusahaan</DialogTitle>
                        <div className="text-muted-foreground mt-2 text-sm">Apakah Anda yakin ingin menghapus perusahaan ini?</div>
                    </DialogHeader>

                    <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-start">
                        <Button variant="destructive" className="w-full text-white sm:w-auto" onClick={onConfirmDelete}>
                            Hapus
                        </Button>

                        <DialogClose asChild>
                            <Button variant="secondary" className="w-full sm:w-auto">
                                Batal
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={openForm} onOpenChange={(isOpen) => !isOpen && resetFormAndClose()}>
                <DialogContent className="max-h-[90vh] w-[92vw] overflow-y-auto rounded-lg sm:max-w-lg">
                    <form onSubmit={onSubmit}>
                        <DialogHeader>
                            <DialogTitle>{selectedCompany ? 'Edit Perusahaan' : 'Tambah Perusahaan'}</DialogTitle>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div>
                                <Label htmlFor="nama_perusahaan">Nama Perusahaan</Label>
                                <Input
                                    id="nama_perusahaan"
                                    name="nama_perusahaan"
                                    value={form.nama_perusahaan}
                                    onChange={handleInputChange}
                                    placeholder="Contoh: PT. Maju Sejahtera"
                                    required
                                />
                            </div>

                            <div>
                                <Label htmlFor="domain">Domain Lengkap</Label>
                                <Input
                                    id="domain"
                                    name="domain"
                                    value={form.domain}
                                    onChange={handleInputChange}
                                    placeholder="Contoh: alpha.registration.tako.co.id"
                                    required
                                />
                                <p className="text-muted-foreground mt-1 text-xs">Masukkan alamat domain lengkap untuk perusahaan ini.</p>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {userRoles.map(({ key, label }) => (
                                    <div key={key}>
                                        <Label htmlFor={key}>{label}</Label>
                                        <select
                                            id={key}
                                            className="bg-background h-10 w-full rounded-md border px-3 py-2 text-sm"
                                            value={form[key as keyof FormState]}
                                            onChange={(e) => handleUserChange(key as keyof FormState, e.target.value)}
                                        >
                                            <option value="">Pilih {label}</option>

                                            {props.users?.map((user: any) => (
                                                <option key={user.id} value={user.id}>
                                                    {user.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>

                            <div>
                                <Label htmlFor="notify_1">Notifikasi Email 1</Label>
                                <Input
                                    id="notify_1"
                                    name="notify_1"
                                    value={form.notify_1}
                                    onChange={handleInputChange}
                                    placeholder="email1@contoh.com, email2@contoh.com"
                                />
                            </div>

                            <div>
                                <Label htmlFor="notify_2">Notifikasi Email 2</Label>
                                <Input
                                    id="notify_2"
                                    name="notify_2"
                                    value={form.notify_2}
                                    onChange={handleInputChange}
                                    placeholder="email3@contoh.com, email4@contoh.com"
                                />
                            </div>

                            <div>
                                <ResettableDropzoneImage
                                    key={selectedCompany ? selectedCompany.id : 'new'}
                                    label="Upload Logo Perusahaan"
                                    isRequired={false}
                                    onFileChange={setCompanyLogoFile}
                                    existingFile={
                                        form.logo_url
                                            ? {
                                                  nama_file: form.logo_url.split('/').pop() ?? 'logo.png',
                                                  path: form.logo_url,
                                              }
                                            : undefined
                                    }
                                />
                            </div>
                        </div>

                        <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-start">
                            <Button type="submit" className="w-full sm:w-auto">
                                {selectedCompany ? 'Update' : 'Create'}
                            </Button>

                            <DialogClose asChild>
                                <Button variant="secondary" type="button" className="w-full sm:w-auto">
                                    Batal
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
