/* eslint-disable @typescript-eslint/no-explicit-any */
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { MasterSupplier, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { columns } from './table/columns';
import { DataTable } from './table/data-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Data Supplier',
        href: '/supplier',
    },
];

export default function MasterSupplierPage() {
    const { suppliers, flash } = usePage().props as unknown as { suppliers: MasterSupplier[]; flash: { success?: string; error?: string } };
    const page = usePage();

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    useEffect(() => {
        const errors = (page.props as any)?.errors;
        if (errors && (errors.status === 403 || errors.code === 403)) {
            toast.error('Anda tidak memiliki akses ke data ini.');
        }
    }, [page.props]);

    const [openDelete, setOpenDelete] = useState(false);
    const [supplierIdToDelete, setSupplierIdToDelete] = useState<number | null>(null);
    const supplierToDelete = suppliers.find((s) => s.id === supplierIdToDelete);

    const onDeleteClick = (id: number) => {
        setSupplierIdToDelete(id);
        setOpenDelete(true);
    };

    const onConfirmDelete = () => {
        if (supplierIdToDelete !== null) {
            router.delete(`/master-supplier/${supplierIdToDelete}`, {
                onSuccess: () => {
                    setOpenDelete(false);
                    setSupplierIdToDelete(null);
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Supplier" />
            <div className="md:p-4">
                <DataTable columns={columns(onDeleteClick)} data={suppliers} />
            </div>

            <Dialog open={openDelete} onOpenChange={setOpenDelete}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Data</DialogTitle>
                        <div className="mt-2">
                            Data <span className="font-bold text-white">{supplierToDelete?.nama_cust ?? 'Tidak ditemukan'}</span> akan dihapus. Apakah
                            Anda yakin?
                        </div>
                    </DialogHeader>
                    <div className="flex gap-2">
                        <Button type="button" variant="destructive" onClick={onConfirmDelete}>
                            Hapus
                        </Button>
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Close
                            </Button>
                        </DialogClose>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
