import { Head } from '@inertiajs/react';

export default function FilledAlready() {
    return (
        <>
            <Head title="Data Supplier" />
            <div className="flex min-h-screen items-center justify-center">
                <div className="space-y-4 text-center">
                    <h1 className="text-3xl font-bold text-red-600">Form sudah diisi</h1>
                    <p className="text-black dark:text-white">Terima kasih! Data supplier sudah dikirim dan tidak bisa diubah lagi.</p>
                </div>
            </div>
        </>
    );
}
