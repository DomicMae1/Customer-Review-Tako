import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, MasterSupplier } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import ViewSupplierForm from './data-form-view';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master Supplier',
        href: '/supplier',
    },
    {
        title: 'View Supplier',
        href: '#',
    },
];

export default function PaymentsEdit() {
    const { props } = usePage();
    const { supplier } = props as unknown as { supplier: MasterSupplier };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="View Supplier" />
            <div className="p-4">
                <ViewSupplierForm supplier={supplier} />
            </div>
        </AppLayout>
    );
}
