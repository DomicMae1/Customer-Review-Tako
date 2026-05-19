/* eslint-disable react-hooks/rules-of-hooks */
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useMediaQuery } from '@/hooks/use-media-query';
import { MasterCustomer } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { MoreHorizontal } from 'lucide-react';
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

        const response = await axios.get(`/customer/${id}/pdf`, {
            responseType: 'blob', // PENTING: Agar respon dibaca sebagai file
        });

        // Buat URL sementara untuk blob
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;

        // Ambil nama file dari header (jika tersedia)
        const contentDisposition = response.headers['content-disposition'];
        let fileName = `Data Customer ${nama_perusahaan}.pdf`;
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

const deleteCustomer = async (id: number) => {
    const result = await Swal.fire({
        title: 'Hapus Customer?',
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

    router.delete(`/customer/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Data berhasil dihapus!');
        },
        onError: () => {
            toast.error('Gagal menghapus data.');
        },
    });
};

export const columns = (): ColumnDef<MasterCustomer>[] => {
    if (typeof window !== 'undefined') {
        const hasReloaded = localStorage.getItem('hasReloadedCustomerPage');

        if (!hasReloaded) {
            localStorage.setItem('hasReloadedCustomerPage', 'true');
            window.location.reload();
        }
    }

    return [
        {
            accessorKey: 'nama_perusahaan',
            header: ({ column }) => (
                <div
                    className="cursor-pointer text-sm font-medium select-none md:px-2 md:py-2"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Ownership
                </div>
            ),
            cell: ({ row }) => <div className="text-sm md:min-w-[150px] md:truncate md:px-2 md:py-2">{row.original.nama_perusahaan ?? '-'}</div>,
        },
        {
            accessorKey: 'creator_name',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">Disubmit oleh</div>,
            cell: ({ row }) => <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.creator_name || '-'}</div>,
        },
        {
            accessorKey: 'nama_customer',
            header: ({ column }) => (
                <div
                    className="cursor-pointer text-sm font-medium select-none md:px-2 md:py-2"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Nama Customer
                </div>
            ),
            cell: ({ row }) => <div className="text-sm md:min-w-[150px] md:truncate md:px-2">{row.original.nama_customer || '-'}</div>,
        },
        {
            accessorKey: 'no_telp_pic',
            header: () => <div className="text-sm font-medium md:px-2 md:py-2">No Telp PIC</div>,
            cell: ({ row }) => <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.no_telp_personal || '-'}</div>,
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
        {
            id: 'actions',
            header: () => <div className="text-right text-sm font-medium md:px-2 md:py-2">{/* kosong agar align */}</div>,
            cell: ({ row }) => {
                const customer = row.original;
                const { auth } = usePage().props;
                const currentUser = auth.user;

                const currentUserRole = currentUser.roles?.[0]?.name;
                const isAdmin = currentUserRole === 'admin';

                const canEdit =
                    !customer.submit_1_timestamps &&
                    (customer.user_id === currentUser.id || (customer.creator?.role && currentUserRole && customer.creator.role === currentUserRole));

                const isDesktop = useMediaQuery('(min-width: 768px)');

                if (isDesktop) {
                    return (
                        <div className="flex justify-end">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" className="h-8 w-8 p-0">
                                        <MoreHorizontal className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end">
                                    <Link href={`/customer/${customer.id}`}>
                                        <DropdownMenuItem>View Customer</DropdownMenuItem>
                                    </Link>

                                    {canEdit && (
                                        <Link href={`/customer/${customer.id}/edit`}>
                                            <DropdownMenuItem>Edit Customer</DropdownMenuItem>
                                        </Link>
                                    )}

                                    <DropdownMenuItem onClick={() => customer.id && downloadPdf(customer.id, customer.nama_perusahaan)}>
                                        Download PDF
                                    </DropdownMenuItem>

                                    {isAdmin && (
                                        <DropdownMenuItem
                                            className="cursor-pointer text-red-600 focus:text-red-600"
                                            onClick={() => customer.id && deleteCustomer(customer.id)}
                                        >
                                            Hapus Customer
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                }

                return (
                    <div className="flex flex-col gap-2 pt-2">
                        <Link href={`/customer/${customer.id}`}>
                            <Button size="sm" variant="outline" className="w-full justify-center dark:border-white">
                                View Customer
                            </Button>
                        </Link>

                        {canEdit && (
                            <Link href={`/customer/${customer.id}/edit`}>
                                <Button className="w-full justify-center">Edit Customer</Button>
                            </Link>
                        )}

                        <Button
                            size="sm"
                            variant="outline"
                            className="w-full justify-center dark:border-white"
                            onClick={() => customer.id && downloadPdf(customer.id, customer.nama_perusahaan)}
                        >
                            Download PDF
                        </Button>

                        {isAdmin && (
                            <Button
                                className="cursor-pointer bg-red-500 text-white"
                                asChild
                                onClick={(e) => {
                                    const confirmed = window.confirm('Apakah anda yakin ingin menghapus data tersebut?');
                                    if (!confirmed) {
                                        e.preventDefault();
                                    }
                                }}
                            >
                                <Link
                                    href={`/customer/${customer.id}`}
                                    method="delete"
                                    as="button"
                                    onSuccess={() => toast.success('Data berhasil dihapus!')}
                                >
                                    Hapus Customer
                                </Link>
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ];
};
