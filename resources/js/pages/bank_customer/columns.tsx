/* eslint-disable @typescript-eslint/no-explicit-any */
import { BankCustomer } from '@/types';
import { ColumnDef } from '@tanstack/react-table';

export const bankCustomerColumns: ColumnDef<BankCustomer>[] = [
    {
        accessorKey: 'nama_perusahaan',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Nama Perusahaan</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[180px] md:truncate md:px-2">{row.original.nama_perusahaan}</div>
        ),
    },
    {
        accessorKey: 'kategori_usaha',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Kategori Usaha</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[130px] md:truncate md:px-2">{row.original.kategori_usaha}</div>
        ),
    },
    {
        accessorKey: 'bentuk_badan_usaha',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Bentuk Badan Usaha</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[160px] md:truncate md:px-2">{row.original.bentuk_badan_usaha}</div>
        ),
    },
    {
        accessorKey: 'kota',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Kota</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[100px] md:truncate md:px-2">{row.original.kota}</div>
        ),
    },
    {
        accessorKey: 'no_telp',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">No Telp</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.no_telp}</div>
        ),
    },
    {
        accessorKey: 'npwp',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">NPWP</div>,
        cell: ({ row }) => (
            <div className="font-mono text-sm md:min-w-[140px] md:truncate md:px-2">{row.original.npwp}</div>
        ),
    },
    {
        accessorKey: 'npwp_16',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">NPWP 16</div>,
        cell: ({ row }) => (
            <div className="font-mono text-sm md:min-w-[140px] md:truncate md:px-2">{row.original.npwp_16}</div>
        ),
    },
    {
        accessorKey: 'nib',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">NIB</div>,
        cell: ({ row }) => (
            <div className="font-mono text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.nib}</div>
        ),
    },
    {
        accessorKey: 'pic',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">PIC</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[130px] md:truncate md:px-2">{row.original.pic}</div>
        ),
    },
    {
        accessorKey: 'jabatan_pic',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Jabatan PIC</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.jabatan_pic}</div>
        ),
    },
    {
        accessorKey: 'no_telp_pic',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">No Telp PIC</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[120px] md:truncate md:px-2">{row.original.no_telp_pic}</div>
        ),
    },
    {
        accessorKey: 'email_pic',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Email PIC</div>,
        cell: ({ row }) => (
            <div className="text-sm md:min-w-[160px] md:truncate md:px-2">{row.original.email_pic}</div>
        ),
    },
    {
        accessorKey: 'entitas',
        header: () => <div className="text-sm font-medium md:px-2 md:py-2">Entitas</div>,
        cell: ({ row }) => {
            const isLengkap = row.original.entitas === 'Lengkap';
            return (
                <div className="md:px-2">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                            isLengkap
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                        }`}
                    >
                        {row.original.entitas}
                    </span>
                </div>
            );
        },
        filterFn: (row, _columnId, filterValue) => {
            if (!filterValue || filterValue === 'all') return true;
            return row.original.entitas === filterValue;
        },
    },
];
