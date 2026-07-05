/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable react-hooks/rules-of-hooks */
import { Button } from '@/components/ui/button';
import { useMediaQuery } from '@/hooks/use-media-query';
import { MasterSupplier } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { Download, Eye, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import Swal from 'sweetalert2';

const downloadPdf = async (id: number, nama_perusahaan: string) => {
    try {
        // Beri feedback ke user bahwa proses sedang berjalan
        const result = await Swal.fire({
            title: 'Download PDF?',
            text: 'Sedang memproses PDF. Ini mungkin memakan waktu beberapa detik.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#dc2626',
        });

        if (!result.isConfirmed) return;

        const response = await axios.get(`/supplier/${id}/pdf`, {
            responseType: 'blob', // PENTING: Agar respon dibaca sebagai file
        });

        // Buat URL sementara untuk blob
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;

        // Ambil nama file dari header (jika tersedia)
        const contentDisposition = response.headers['content-disposition'];
        let fileName = `Data Supplier ${nama_perusahaan}.pdf`;
        if (contentDisposition) {
            const fileNameMatch = contentDisposition.match(/filename\*?=(?:UTF-8'')?["']?([^;"']+)/i);

            if (fileNameMatch?.[1]) {
                fileName = decodeURIComponent(fileNameMatch[1].trim());
            }
        }

        link.setAttribute('download', fileName);
        document.body.appendChild(link);
        link.click();

        // Cleanup memori
        link.remove();
        window.URL.revokeObjectURL(url);
        toast.success('PDF berhasil diunduh.');
    } catch (error) {
        console.error('Download Error:', error);
        toast.error('Gagal mengunduh PDF. Silakan coba lagi nanti.');
    }
};

const deleteSupplier = async (id: number) => {
    const result = await Swal.fire({
        title: 'Hapus Supplier?',
        text: 'Apakah anda yakin ingin menghapus data tersebut?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    router.delete(`/supplier/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Data berhasil dihapus!');
        },
        onError: () => {
            toast.error('Gagal menghapus data.');
        },
    });
};

export const columns = (): ColumnDef<MasterSupplier>[] => {
    if (typeof window !== 'undefined') {
        const hasReloaded = localStorage.getItem('hasReloadedSupplierPage');

        if (!hasReloaded) {
            localStorage.setItem('hasReloadedSupplierPage', 'true');
            window.location.reload();
        }
    }

    return [
        {
            id: 'actions',
            header: () => <div className="text-center text-sm font-medium md:px-2 md:py-2"></div>,
            cell: ({ row }) => {
                const supplier = row.original;
                const { auth } = usePage().props as any;
                const currentUser = auth.user;
                const userPermissions = currentUser?.permissions || [];
                const currentUserRole = currentUser?.roles?.[0]?.name;
                const isAdmin = currentUserRole === 'admin';

                const hasPermission = (perm: string) => isAdmin || userPermissions.includes(perm);

                const canView = hasPermission('supplier.view');
                const canEditPermission = hasPermission('supplier.update');
                const canPdf = hasPermission('supplier.pdf');
                const canDelete = hasPermission('supplier.delete');

                // Keep existing business logic for edit: only if not submitted yet, and creator matches (unless admin)
                const canEdit = canEditPermission && (
                    isAdmin ||
                    (!supplier.submit_1_timestamps && (supplier.user_id === currentUser.id || (supplier.creator?.role && currentUserRole && supplier.creator.role === currentUserRole)))
                );

                const isDesktop = useMediaQuery('(min-width: 768px)');

                const iconButtonClass =
                    'h-8 w-8 rounded-md border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-gray-200 dark:hover:bg-neutral-800';

                const mobileIconButtonClass =
                    'h-9 w-9 rounded-md border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-gray-200 dark:hover:bg-neutral-800';

                if (isDesktop) {
                    return (
                        <div className="flex items-center justify-start gap-2">
                            {canView && (
                                <Link href={`/supplier/${supplier.id}`}>
                                    <Button variant="ghost" size="icon" title="View Supplier" className={iconButtonClass}>
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                </Link>
                            )}

                            {canEdit && (
                                <Link href={`/supplier/${supplier.id}/edit`}>
                                    <Button variant="ghost" size="icon" title="Edit Supplier" className={iconButtonClass}>
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                </Link>
                            )}

                            {canPdf && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Download PDF"
                                    className={iconButtonClass}
                                    onClick={() => supplier.id && downloadPdf(supplier.id, supplier.nama_perusahaan)}
                                >
                                    <Download className="h-4 w-4" />
                                </Button>
                            )}

                            {canDelete && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Hapus Supplier"
                                    className="h-8 w-8 rounded-md border border-red-300 bg-white text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 dark:border-red-800 dark:bg-neutral-900 dark:hover:bg-red-950/30"
                                    onClick={() => supplier.id && deleteSupplier(supplier.id)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    );
                }

                return (
                    <div className="flex items-center justify-end gap-2">
                        {canView && (
                            <Link href={`/supplier/${supplier.id}`}>
                                <Button size="icon" variant="ghost" title="View Supplier" className={mobileIconButtonClass}>
                                    <Eye className="h-4 w-4 text-gray-700" />
                                </Button>
                            </Link>
                        )}

                        {canPdf && (
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Download PDF"
                                className={mobileIconButtonClass}
                                onClick={() => supplier.id && downloadPdf(supplier.id, supplier.nama_perusahaan)}
                            >
                                <Download className="h-4 w-4 text-gray-700 dark:text-gray-200" />
                            </Button>
                        )}

                        {canEdit && (
                            <Link href={`/supplier/${supplier.id}/edit`}>
                                <Button size="icon" variant="ghost" title="Edit Supplier" className={mobileIconButtonClass}>
                                    <Pencil className="h-4 w-4 text-gray-700 dark:text-gray-200" />
                                </Button>
                            </Link>
                        )}

                        {canDelete && (
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Hapus Supplier"
                                className="h-9 w-9 rounded-md border border-red-300 bg-white text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 dark:border-red-800 dark:bg-neutral-900 dark:hover:bg-red-950/30"
                                onClick={() => supplier.id && deleteSupplier(supplier.id)}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'nama_supplier',
            header: ({ column }) => (
                <div
                    className="cursor-pointer text-sm font-medium select-none md:px-2 md:py-2"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Nama Supplier
                </div>
            ),
            cell: ({ row }) => <div className="text-sm md:min-w-[150px] md:truncate md:px-2">{row.original.nama_supplier || '-'}</div>,
        },
        {
            accessorKey: 'supplier_category',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">Kategori Supplier</div>,
            cell: ({ row }) => <div className="text-sm md:min-w-[130px] md:truncate md:px-2 capitalize">{row.original.supplier_category || '-'}</div>,
        },
        {
            accessorKey: 'creator_name',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">Dibuat oleh</div>,
            cell: ({ row }) => <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.creator_name || '-'}</div>,
        },
        {
            accessorKey: 'nama_perusahaan',
            header: ({ column }) => (
                <div
                    className="cursor-pointer text-sm font-medium select-none md:px-2 md:py-2"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Pemilik Data
                </div>
            ),
            cell: ({ row }) => <div className="text-sm md:min-w-[150px] md:truncate md:px-2 md:py-2">{row.original.nama_perusahaan ?? '-'}</div>,
        },
        {
            accessorKey: 'no_telp_pic',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">No Telp PIC</div>,
            cell: ({ row }) => <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.no_telp_personal || '-'}</div>,
        },
        {
            accessorKey: 'status',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">Status Review</div>,
            cell: ({ row }) => {
                const status = row.original.status?.toLowerCase();
                let displayText = '-';
                let textColor = 'text-muted-foreground';

                if (status === 'rejected') {
                    displayText = 'Bermasalah';
                    textColor = 'text-red-600';
                } else if (status === 'approved') {
                    displayText = 'Aman';
                    textColor = 'text-green-600';
                } else if (status) {
                    displayText = status;
                }

                return <div className={`text-sm font-semibold md:min-w-[100px] md:px-2 ${textColor}`}>{displayText}</div>;
            },
        },
        {
            accessorKey: 'keterangan_status',
            accessorFn: (row) => {
                return {
                    sort: row.tanggal_status ? new Date(row.tanggal_status).getTime() : 0,
                    label: row.status_label ?? null,
                };
            },
            sortingFn: (rowA, rowB, columnId) => {
                return rowA.getValue(columnId).sort - rowB.getValue(columnId).sort;
            },
            filterFn: (row, columnId, filterValue) => {
                const value = row.getValue(columnId);
                if (!value.label) return false;
                return value.label.toLowerCase() === filterValue.toLowerCase();
            },

            header: ({ column }) => (
                <div
                    className="cursor-pointer text-sm font-medium select-none md:px-2 md:py-2"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Keterangan Status
                </div>
            ),

            cell: ({ row }) => {
                const tanggal = row.original.tanggal_status;
                const label = row.original.status_label;
                const nama_user = row.original.nama_user;

                if (!tanggal && !label) return <div className="text-sm">-</div>;

                const isInput = label === 'diinput';

                const dateObj = new Date(tanggal);
                const tanggalFormat = dateObj
                    .toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    })
                    .replace(/\./g, '/');

                const jamMenit = dateObj
                    .toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    })
                    .replace('.', ':');

                return (
                    <div className="text-sm md:min-w-[200px] md:truncate md:px-2">
                        <span>
                            {label}
                            {!isInput && nama_user ? ' oleh ' : ' '}
                            {!isInput && nama_user && <strong>{nama_user}</strong>}
                            {' pada '}
                            <strong>{`${tanggalFormat} ${jamMenit} WIB`}</strong>
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'status_2_timestamps',
            header: () => <div className="hidden"></div>,
            cell: () => null,

            filterFn: (row, columnId, filterValue) => {
                const value = row.getValue(columnId);

                if (filterValue === 'sudah') {
                    return value !== null && value !== '' && value !== undefined;
                }

                if (filterValue === 'belum') {
                    return value === null || value === '' || value === undefined;
                }

                // untuk "all" atau default
                return true;
            },
        },
    ];
};
