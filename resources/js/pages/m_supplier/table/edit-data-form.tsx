import AppLayout from '@/layouts/app-layout';
import { Attachment, Auth, type BreadcrumbItem, MasterSupplier } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import SupplierForm from './data-form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master Supplier',
        href: '/supplier',
    },
    {
        title: 'Edit Supplier',
        href: '#',
    },
];

export default function EditSupplier() {
    const { props } = usePage();
    const { supplier, auth, attachments } = props as unknown as { supplier: MasterSupplier; auth: Auth; attachments: Attachment };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Supplier" />
            <div className="p-4">
                <SupplierForm supplier={supplier} auth={auth} attachment={attachments} />
            </div>
        </AppLayout>
    );
}
