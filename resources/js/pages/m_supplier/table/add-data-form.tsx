import AppLayout from '@/layouts/app-layout';
import { Auth, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import SupplierForm from './data-form';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master Supplier',
        href: '/supplier',
    },
    {
        title: 'Create Supplier',
        href: '#',
    },
];

export default function CreateSupplier() {
    const { props } = usePage();
    const { auth } = props as unknown as { auth: Auth };
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Supplier" />
            <div className="p-4">
                <SupplierForm auth={auth} />
            </div>
        </AppLayout>
    );
}
