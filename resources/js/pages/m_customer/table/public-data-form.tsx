/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable @typescript-eslint/no-unused-vars */
import { ResettableDropzone } from '@/components/ResettableDropzone';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Toaster } from '@/components/ui/sonner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Attachment, AttachmentType, MasterCustomer } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { File, Loader2, Moon, Sun, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';
import { NumericFormat } from 'react-number-format';
import { toast } from 'sonner';

interface UploadedFileState {
    path: string;
    nama_file: string;
}

export default function PublicCustomerForm({
    customer,
    onSuccess,
    isFilled = false,
}: {
    customer?: MasterCustomer;
    onSuccess?: () => void;
    isFilled?: boolean;
}) {
    const { customer_name, token, user_id, id_perusahaan, company, attachmentRules } = usePage().props as unknown as {
        customer_name: string;
        token: string;
        user_id: number;
        id_perusahaan: number;
        company?: {
            id?: number;
            name?: string;
            logo?: string | null;
        };
        attachmentRules?: {
            is_npwp: boolean;
            is_nib: boolean;
            is_sptkp: boolean;
            is_ktp: boolean;
        };
    };

    const rules = attachmentRules ?? {
        is_npwp: true,
        is_nib: true,
        is_sptkp: false,
        is_ktp: true,
    };

    const companyLogo = company?.logo ? (company.logo.startsWith('http') ? company.logo : `/storage/${company.logo}`) : null;

    const { data, setData, processing, errors } = useForm<MasterCustomer>({
        id: customer?.id || null,
        kategori_usaha: customer?.kategori_usaha || '',
        nama_perusahaan: customer?.nama_perusahaan || '',
        bentuk_badan_usaha: customer?.bentuk_badan_usaha || '',
        alamat_lengkap: customer?.alamat_lengkap || '',
        kota: customer?.kota || '',
        no_telp: Array.isArray(customer?.no_telp) ? (customer.no_telp.length > 0 ? customer.no_telp : ['']) : (customer?.no_telp ? [customer.no_telp] : ['']),
        no_fax: customer?.no_fax ?? null,
        alamat_penagihan: customer?.alamat_penagihan || '',
        email: customer?.email || '',
        website: customer?.website || '',
        top: customer?.top || '',
        no_npwp: customer?.no_npwp || '',
        no_npwp_16: customer?.no_npwp_16 || '',
        nib: customer?.nib || '',
        jenis_perusahaan: customer?.jenis_perusahaan || '',
        nama_pj: customer?.nama_pj || '',
        no_ktp_pj: customer?.no_ktp_pj || '',
        no_telp_pj: customer?.no_telp_pj || '',
        nama_personal: customer?.nama_personal || '',
        jabatan_personal: customer?.jabatan_personal || '',
        no_telp_personal: Array.isArray(customer?.no_telp_personal) ? (customer.no_telp_personal.length > 0 ? customer.no_telp_personal : ['']) : (customer?.no_telp_personal ? [customer.no_telp_personal] : ['']),
        email_personal: customer?.email_personal || '',
        keterangan_reject: customer?.keterangan_reject || '',
        user_id: user_id,
        id_perusahaan: id_perusahaan,
        approved_1_by: customer?.approved_1_by ?? null,
        approved_2_by: customer?.approved_2_by ?? null,
        rejected_1_by: customer?.rejected_1_by ?? null,
        rejected_2_by: customer?.rejected_2_by ?? null,
        keterangan: customer?.keterangan || '',
        tgl_approval_1: customer?.tgl_approval_1 || null,
        tgl_approval_2: customer?.tgl_approval_2 || null,
        tgl_customer: customer?.tgl_customer || null,
        attachments: customer?.attachments || [],
    });

    const [isLoading, setIsLoading] = useState(false);

    const [theme, setTheme] = useState<'light' | 'dark'>(() => (localStorage.getItem('theme') as 'light' | 'dark') || 'light');

    useEffect(() => {
        const root = window.document.documentElement;

        root.classList.remove('light', 'dark');
        root.classList.add(theme);

        localStorage.setItem('theme', theme);
    }, [theme]);

    const toggleTheme = () => {
        setTheme(theme === 'light' ? 'dark' : 'light');
    };

    const [lainKategori, setLainKategori] = useState(() => {
        const isCustom = customer?.kategori_usaha && !['kontraktor', 'toko', 'industri', 'dealer'].includes(customer.kategori_usaha);
        return isCustom ? customer.kategori_usaha : '';
    });
    const [showLainKategori, setShowLainKategori] = useState(() => {
        return !!(customer?.kategori_usaha && !['kontraktor', 'toko', 'industri', 'dealer'].includes(customer.kategori_usaha));
    });

    const [errors_kategori, setErrors] = useState<{
        kategori_usaha?: string;
        lain_kategori?: string;
        nama_perusahaan?: string;
        bentuk_badan_usaha?: string;
        status_perpajakan?: string;
        alamat_lengkap?: string;
        kota?: string;
        no_telp?: string;
        alamat_penagihan?: string;
        email?: string;
        top?: string;
        no_npwp?: string;
        no_npwp_16?: string;
        nib?: string;
        jenis_perusahaan?: string;
        nama_pj?: string;
        no_ktp_pj?: string;
        no_telp_pj?: string;
        nama_personal?: string;
        jabatan_personal?: string;
        no_telp_personal?: string;
        email_personal?: string;
        attachments?: string;
    }>({});

    const [npwpFile, setNpwpFile] = useState<UploadedFileState | null>(null);
    const [nibFile, setNibFile] = useState<UploadedFileState | null>(null);
    const [sppkpFile, setSppkpFile] = useState<UploadedFileState | null>(null);
    const [ktpFile, setKtpFile] = useState<UploadedFileState | null>(null);
    // const [isModalOpen, setIsModalOpen] = useState(false);
    const [isConfirmDialogOpen, setIsConfirmDialogOpen] = useState(false);
    const [pendingPayload, setPendingPayload] = useState<any>(null);

    const handleUploadSuccess = (type: string, file: File | null, response: any) => {
        if (file && response) {
            const fileData = {
                path: response.path, // Path temp dari server
                nama_file: response.nama_file || file.name,
            };
            if (type === 'npwp') setNpwpFile(fileData);
            if (type === 'nib') setNibFile(fileData);
            if (type === 'sppkp') setSppkpFile(fileData);
            if (type === 'ktp') setKtpFile(fileData);
        } else {
            // Jika dihapus
            if (type === 'npwp') setNpwpFile(null);
            if (type === 'nib') setNibFile(null);
            if (type === 'sppkp') setSppkpFile(null);
            if (type === 'ktp') setKtpFile(null);
        }
    };

    useEffect(() => {
        if (isFilled) {
            toast.error('Form sudah diisi sebelumnya. Kamu tidak bisa mengedit data ini lagi.');
            router.visit('/');
        }
    }, [isFilled]);

    useEffect(() => {
        if (!customer) return;
        const isCustom = customer.kategori_usaha && !['kontraktor', 'toko', 'industri', 'dealer'].includes(customer.kategori_usaha);
        setLainKategori(isCustom ? customer.kategori_usaha : '');
        setShowLainKategori(!!isCustom);
    }, [customer]);

    const normalizePhone = (phone?: string | null) => {
        if (!phone) return null;

        const cleaned = phone.replace(/\s/g, '');

        if (cleaned === '+62' || cleaned === '+') {
            return null;
        }

        return phone;
    };

    function formatNpwp16(input: string): string {
        const raw = input.replace(/\D/g, '');
        const parts = [raw.slice(0, 4), raw.slice(4, 8), raw.slice(8, 12), raw.slice(12, 16)].filter(Boolean);
        return parts.join(' ');
    }

    function formatNpwp(input: string) {
        const raw = input.replace(/\D/g, '');
        const parts = [raw.slice(0, 2), raw.slice(2, 5), raw.slice(5, 8), raw.slice(8, 9), raw.slice(9, 12), raw.slice(12, 15)].filter(Boolean);
        return parts
            .map((part, i) => {
                if (i === 3) return '-' + part;
                if (i !== 0) return '.' + part;
                return part;
            })
            .join('');
    }

    let counter = 1;

    async function uploadAttachment(file: File, type: AttachmentType, npwpNumber: string): Promise<Attachment> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('order', String(counter));
        formData.append('npwp_number', npwpNumber);
        formData.append('type', type);
        formData.append('id_perusahaan', String(id_perusahaan));

        const res = await axios.post('/customer/upload-temp', formData);

        counter++;

        return {
            id: 0,
            customer_id: customer?.id ?? 0,
            nama_file: res.data.nama_file,
            path: res.data.path,
            type,
        };
    }

    const submitFinalData = async () => {
        if (!pendingPayload) return;

        setIsConfirmDialogOpen(false);
        setIsLoading(true);

        try {
            const res = await axios.post(route('customer.public.submit'), pendingPayload);
            toast.success(res.data.message ?? 'Data berhasil disimpan.');
            setIsLoading(false);
            window.location.reload();
        } catch (error: any) {
            console.error('Submit Error:', error);
            const msg = error.response?.data?.error || 'Terjadi kesalahan saat menyimpan data.';
            toast.error(msg);
            setIsLoading(false);
        }
    };

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();

        const newErrors: typeof errors_kategori = {};

        setIsLoading(true);
        const showValidationError = (field: keyof typeof errors_kategori, message: string) => {
            setErrors((prev) => ({ ...prev, [field]: message }));
            toast.error(message);
            setIsLoading(false);
        };

        if (pendingPayload) {
            setIsConfirmDialogOpen(true);
            setIsLoading(false);
            return;
        }

        const isCustomerPerorangan = data.bentuk_badan_usaha === 'Customer Perorangan';

        if (!data.kategori_usaha) {
            showValidationError('kategori_usaha', 'Kategori usaha wajib dipilih');
            return;
        }

        if (data.kategori_usaha === 'lain2' && !lainKategori.trim()) {
            showValidationError('lain_kategori', 'Kategori lainnya wajib diisi');
            return;
        }

        if (!data.nama_perusahaan) {
            showValidationError('nama_perusahaan', 'Nama Perusahaan wajib diisi');
            return;
        }

        if (!data.bentuk_badan_usaha) {
            showValidationError('bentuk_badan_usaha', 'Bentuk badan usaha wajib dipilih');
            return;
        }

        if (!data.alamat_lengkap || !data.alamat_lengkap.trim()) {
            showValidationError('alamat_lengkap', 'Alamat lengkap wajib diisi');
            return;
        }

        if (!data.kota || !data.kota.trim()) {
            showValidationError('kota', 'Kota wajib diisi');
            return;
        }

        if (!data.no_telp || !data.no_telp[0] || data.no_telp[0].trim().length <= 3) {
            showValidationError('no_telp', 'No Telpon Perusahaan wajib diisi');
            return;
        }

        if (!data.alamat_penagihan || !data.alamat_penagihan.trim()) {
            showValidationError('alamat_penagihan', 'Alamat Perusahaan wajib diisi');
            return;
        }

        if (!data.email || !data.email.trim()) {
            showValidationError('email', 'Email Perusahaan wajib diisi');
            return;
        }

        if (!data.top || !data.top.trim()) {
            showValidationError('top', 'Term of Payment wajib diisi');
            return;
        }

        if (!data.status_perpajakan) {
            showValidationError('status_perpajakan', 'Status perpajakan wajib dipilih');
            return;
        }

        if (!data.jenis_perusahaan) {
            showValidationError('jenis_perusahaan', 'Jenis Perusahaan wajib dipilih');
            return;
        }

        if (data.jenis_perusahaan === 'Perusahaan Dalam Negeri') {
            if (!data.no_npwp || !data.no_npwp.trim()) {
                showValidationError('no_npwp', 'Nomer NPWP wajib diisi');
                return;
            }

            const npwpOnlyNumber = data.no_npwp.replace(/\D/g, '');
            if (npwpOnlyNumber.length < 15) {
                showValidationError('no_npwp', 'Nomer NPWP belum lengkap');
                return;
            }

            if (!data.no_npwp_16 || !data.no_npwp_16.trim()) {
                showValidationError('no_npwp_16', 'Nomer NPWP 16 wajib diisi');
                return;
            }

            const npwp16OnlyNumber = data.no_npwp_16.replace(/\D/g, '');
            if (npwp16OnlyNumber.length < 16) {
                showValidationError('no_npwp_16', 'Nomer NPWP 16 belum lengkap');
                return;
            }

            if (!data.nib || !data.nib.trim()) {
                showValidationError('nib', 'NIB wajib diisi');
                return;
            }
        } else {
            // Perusahaan Luar Negeri: NPWP/NPWP16/NIB opsional, tapi jika diisi tetap divalidasi
            if (data.no_npwp && data.no_npwp.trim() !== '') {
                const npwpOnlyNumber = data.no_npwp.replace(/\D/g, '');
                if (npwpOnlyNumber.length < 15) {
                    showValidationError('no_npwp', 'Nomer NPWP belum lengkap');
                    return;
                }
            }

            if (data.no_npwp_16 && data.no_npwp_16.trim() !== '') {
                const npwp16OnlyNumber = data.no_npwp_16.replace(/\D/g, '');
                if (npwp16OnlyNumber.length < 16) {
                    showValidationError('no_npwp_16', 'Nomer NPWP 16 belum lengkap');
                    return;
                }
            }
        }

        if (!data.nama_personal || !data.nama_personal.trim()) {
            showValidationError('nama_personal', 'Nama Personal wajib diisi');
            return;
        }

        if (!data.jabatan_personal || !data.jabatan_personal.trim()) {
            showValidationError('jabatan_personal', 'Jabatan Personal wajib diisi');
            return;
        }

        if (!data.no_telp_personal || !data.no_telp_personal[0] || data.no_telp_personal[0].trim().length <= 3) {
            showValidationError('no_telp_personal', 'No Telp Personal wajib diisi');
            return;
        }

        if (!data.email_personal || !data.email_personal.trim()) {
            showValidationError('email_personal', 'Email Personal wajib diisi');
            return;
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        const getFileStatus = (newFile: UploadedFileState | null, type: string) => {
            if (newFile) return true;
            return customer?.attachments?.some((a) => a.type === type);
        };

        if (data.jenis_perusahaan !== 'Perusahaan Luar Negeri' && rules.is_npwp && !getFileStatus(npwpFile, 'npwp')) {
            toast.error('Dokumen NPWP wajib diunggah.');
            setIsLoading(false);
            return;
        }

        if (data.jenis_perusahaan !== 'Perusahaan Luar Negeri' && rules.is_nib && !isCustomerPerorangan && !getFileStatus(nibFile, 'nib')) {
            toast.error('Dokumen NIB wajib diunggah.');
            setIsLoading(false);
            return;
        }

        if (rules.is_sptkp && !getFileStatus(sppkpFile, 'sppkp')) {
            toast.error('Dokumen SPTKP wajib diunggah.');
            setIsLoading(false);
            return;
        }

        if (rules.is_ktp && !getFileStatus(ktpFile, 'ktp')) {
            toast.error('Dokumen KTP wajib diunggah.');
            setIsLoading(false);
            return;
        }

        setErrors(newErrors);
        setIsLoading(true);

        const prepareAttachment = (fileState: UploadedFileState | null, type: string) => {
            if (fileState) {
                return { ...fileState, type, is_new: true };
            }
            const existing = customer?.attachments?.find((a) => a.type === type);
            if (existing) {
                return { path: existing.path, nama_file: existing.nama_file, type, is_new: false };
            }
            return null;
        };

        const rawAttachments = [
            prepareAttachment(npwpFile, 'npwp'),
            isCustomerPerorangan ? null : prepareAttachment(nibFile, 'nib'),
            prepareAttachment(sppkpFile, 'sppkp'),
            prepareAttachment(ktpFile, 'ktp'),
        ].filter(Boolean) as any[];

        const processedAttachments: any[] = [];

        try {
            const processResults = await Promise.all(
                rawAttachments.map(async (att, index) => {
                    if (att.is_new && att.path.startsWith('temp/')) {
                        const processRes = await axios.post('/customer/process-attachment', {
                            path: att.path,
                            nama_file: att.nama_file,
                            id_perusahaan: id_perusahaan,
                            mode: 'medium',
                            customer_id: null,

                            type: att.type,
                            npwp_number: data.no_npwp,
                            increment_order: index + 1,
                        });

                        return {
                            nama_file: processRes.data.nama_file,
                            path: processRes.data.final_path,
                            type: att.type,
                        };
                    } else {
                        return {
                            nama_file: att.nama_file,
                            path: att.path,
                            type: att.type,
                        };
                    }
                }),
            );
            processedAttachments.push(...processResults);
        } catch (err) {
            console.error('Upload gagal:', err);
            toast.error('Gagal upload file. Silakan coba lagi.');
            setIsLoading(false);
            return;
        }

        const finalPayload = {
            ...data,
            no_telp_pj: normalizePhone(data.no_telp_pj),
            attachments: processedAttachments, // Kirim array attachment yang sudah final
        };

        setPendingPayload(finalPayload);
        setIsConfirmDialogOpen(true);
        setIsLoading(false);
    };

    return (
        <>
            <Head title="Data Customer" />
            <Toaster richColors position="bottom-right" />

            <div className="bg-background min-h-screen px-4 py-6 sm:px-6 lg:px-8">
                <div className="relative">
                    {/* Kop perusahaan */}
                    <div className="mb-4 flex justify-start 2xl:absolute 2xl:top-0 2xl:left-0 2xl:mb-0">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-white shadow-sm sm:h-14 sm:w-14 dark:bg-neutral-900">
                                {companyLogo ? (
                                    <img src={companyLogo} alt={company?.name ?? 'Logo Perusahaan'} className="h-full w-full object-contain p-1.5" />
                                ) : (
                                    <div className="text-sm font-semibold text-gray-500">{company?.name?.charAt(0)?.toUpperCase() ?? '-'}</div>
                                )}
                            </div>

                            <h2 className="text-foreground max-w-[180px] truncate text-base tracking-tight sm:text-lg">{company?.name ?? '-'}</h2>
                        </div>
                    </div>

                    {/* Kotak form */}
                    <div className="mx-auto max-w-7xl rounded-2xl p-4 xl:border 2xl:mt-0">
                        <div className="mb-6 flex items-start justify-between gap-4">
                            <h1 className="min-w-0 text-2xl font-semibold sm:text-3xl">{customer ? 'Edit Data Customer' : 'Data Customer'}</h1>

                            <Button variant="ghost" size="icon" onClick={toggleTheme} type="button" className="shrink-0 rounded-full">
                                {theme === 'light' ? <Moon className="h-7 w-7" /> : <Sun className="h-7 w-7" />}
                                <span className="sr-only">Toggle theme</span>
                            </Button>
                        </div>

                        <form onSubmit={handleSubmit}>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div className="w-full">
                                        <Label htmlFor="kategori_usaha">
                                            Kategori Usaha <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={showLainKategori ? 'lain2' : data.kategori_usaha}
                                            onValueChange={(value) => {
                                                if (value === 'lain2') {
                                                    setShowLainKategori(true); // tampilkan input tambahan
                                                    setLainKategori(''); // kosongkan dulu input lain-lain
                                                    setData('kategori_usaha', ''); // kosongkan di data
                                                } else {
                                                    setShowLainKategori(false);
                                                    setLainKategori('');
                                                    setData('kategori_usaha', value);
                                                }

                                                setErrors((prev) => ({
                                                    ...prev,
                                                    kategori_usaha: undefined,
                                                    lain_kategori: undefined,
                                                }));
                                            }}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih Kategori Usaha" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="kontraktor">Kontraktor</SelectItem>
                                                <SelectItem value="toko">Toko</SelectItem>
                                                <SelectItem value="industri">Industri</SelectItem>
                                                <SelectItem value="dealer">Dealer</SelectItem>
                                                <SelectItem value="lain2">Lain-Lain</SelectItem>
                                            </SelectContent>
                                        </Select>

                                        {showLainKategori && (
                                            <div className="mt-2">
                                                <Label htmlFor="lain_kategori">Kategori Usaha Lainnya</Label>
                                                <input
                                                    type="text"
                                                    id="lain_kategori"
                                                    value={lainKategori}
                                                    onChange={(e) => {
                                                        const value = e.target.value;
                                                        setLainKategori(value);
                                                        setData('kategori_usaha', value); // simpan ke data utama
                                                        setErrors((prev) => ({ ...prev, lain_kategori: undefined }));
                                                    }}
                                                    className="focus:border-primary mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:ring"
                                                    placeholder="Isi kategori usaha lainnya"
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="nama_perusahaan">
                                            Nama Perusahaan <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="nama_perusahaan"
                                            value={data.nama_perusahaan}
                                            onChange={(e) => setData('nama_perusahaan', e.target.value)}
                                            placeholder="Masukkan nama perusahaan"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="jenis_perusahaan">
                                            Jenis Perusahaan <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.jenis_perusahaan || ''}
                                            onValueChange={(value) => {
                                                setData('jenis_perusahaan', value);
                                                setErrors((prev) => ({
                                                    ...prev,
                                                    jenis_perusahaan: undefined,
                                                }));
                                            }}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih Jenis Perusahaan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Perusahaan Dalam Negeri">Perusahaan Dalam Negeri</SelectItem>
                                                <SelectItem value="Perusahaan Luar Negeri">Perusahaan Luar Negeri</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="bentuk_badan_usaha">
                                            Bentuk Badan Usaha <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={data.bentuk_badan_usaha}
                                            onValueChange={(value) => {
                                                setData('bentuk_badan_usaha', value);

                                                setErrors((prev) => ({
                                                    ...prev,
                                                    bentuk_badan_usaha: undefined,
                                                }));
                                            }}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih Bentuk Badan Usaha" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Penanaman Modal Asing">Penanaman Modal Asing (PMA)</SelectItem>

                                                <SelectItem value="Penanaman Modal Dalam Negeri">Penanaman Modal Dalam Negeri (PMDN)</SelectItem>

                                                <SelectItem value="Perseroan Terbatas">Perseroan Terbatas (PT)</SelectItem>

                                                <SelectItem value="Commanditaire Vennootschap">Commanditaire Vennootschap (CV)</SelectItem>

                                                <SelectItem value="Usaha Dagang">Usaha Dagang (UD)</SelectItem>

                                                <SelectItem value="Perusahaan Perseorangan">Perusahaan Perseorangan (PO)</SelectItem>

                                                <SelectItem value="Customer Perorangan">Customer Perorangan (CP)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="alamat_lengkap">
                                            Alamat Lengkap <span className="text-red-500">*</span>
                                        </Label>
                                        <Textarea
                                            id="alamat_lengkap"
                                            value={data.alamat_lengkap}
                                            onChange={(e) => setData('alamat_lengkap', e.target.value)}
                                            placeholder="Masukkan Alamat Lengkap"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="kota">
                                            Kota <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="kota"
                                            value={data.kota}
                                            onChange={(e) => setData('kota', e.target.value)}
                                            placeholder="Masukkan Kota"
                                        />
                                    </div>
                                    <div className="w-full space-y-2">
                                        <Label>
                                            Nomor Telp Perusahaan <span className="text-red-500">*</span>
                                        </Label>
                                        {data.no_telp.map((phone, idx) => (
                                            <div key={idx} className="flex items-center gap-2">
                                                <div className="flex-1">
                                                    <PhoneInput
                                                        defaultCountry="id"
                                                        value={phone || ''}
                                                        onChange={(phoneVal) => {
                                                            const newPhones = [...data.no_telp];
                                                            newPhones[idx] = phoneVal;
                                                            setData('no_telp', newPhones);
                                                        }}
                                                        inputClassName={cn(
                                                            'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                            'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                                        )}
                                                        placeholder="Masukkan nomor telepon"
                                                    />
                                                </div>
                                                {data.no_telp.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="h-9 w-9 shrink-0 border-red-200 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 dark:border-transparent dark:bg-destructive dark:text-destructive-foreground dark:hover:bg-destructive/90"
                                                        onClick={() => {
                                                            const newPhones = data.no_telp.filter((_, i) => i !== idx);
                                                            setData('no_telp', newPhones);
                                                        }}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-1"
                                            onClick={() => setData('no_telp', [...data.no_telp, ''])}
                                        >
                                            <Plus className="mr-1.5 h-3.5 w-3.5" /> Tambah Nomor
                                        </Button>
                                    </div>

                                    <div className="w-full">
                                        <Label htmlFor="no_fax">Nomor Fax</Label>
                                        <NumericFormat
                                            id="no_fax"
                                            value={data.no_fax}
                                            onChange={(e) => setData('no_fax', e.target.value)}
                                            className={cn(
                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                            )}
                                            placeholder="Enter nomor fax (optional)"
                                            allowNegative={false}
                                            decimalScale={0}
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="alamat_penagihan">
                                            Alamat Penagihan <span className="text-red-500">*</span>
                                        </Label>
                                        <Textarea
                                            id="alamat_penagihan"
                                            value={data.alamat_penagihan}
                                            onChange={(e) => setData('alamat_penagihan', e.target.value)}
                                            placeholder="Masukkan Alamat Lengkap"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="email">
                                            Email <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="Masukkan email"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="website">Alamat Website</Label>
                                        <Input
                                            id="website"
                                            value={data.website}
                                            onChange={(e) => setData('website', e.target.value)}
                                            placeholder="Masukkan website (optional)"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="top">
                                            Terms of Payment <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="top"
                                            value={data.top}
                                            onChange={(e) => setData('top', e.target.value)}
                                            placeholder="Masukkan Terms of Payment"
                                        />
                                    </div>

                                    <div className="w-full">
                                        <Label htmlFor="status_perpajakan">
                                            Status Perpajakan <span className="text-red-500">*</span>
                                        </Label>
                                        <Select value={data.status_perpajakan} onValueChange={(value) => setData('status_perpajakan', value)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih Status Perpajakan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pkp">PKP</SelectItem>
                                                <SelectItem value="non-pkp">NON PKP</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="no_npwp">
                                            Nomor NPWP {data.jenis_perusahaan !== 'Perusahaan Luar Negeri' && <span className="text-red-500">*</span>}
                                        </Label>
                                        <input
                                            type="text"
                                            id="no_npwp"
                                            value={data.no_npwp ?? ''}
                                            onChange={(e) => setData('no_npwp', formatNpwp(e.target.value))}
                                            placeholder="Masukkan nomor NPWP"
                                            className={cn(
                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                            )}
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="no_npwp_16">
                                            Nomor NPWP (16 Digit) {data.jenis_perusahaan !== 'Perusahaan Luar Negeri' && <span className="text-red-500">*</span>}
                                        </Label>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={19}
                                            id="no_npwp_16"
                                            value={data.no_npwp_16 ?? ''}
                                            onChange={(e) => setData('no_npwp_16', formatNpwp16(e.target.value))}
                                            placeholder="Masukkan nomor NPWP 16 digit"
                                            className={cn(
                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                            )}
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="nib">
                                            Nomor NIB {data.jenis_perusahaan !== 'Perusahaan Luar Negeri' && <span className="text-red-500">*</span>}
                                        </Label>
                                        <input
                                            type="text"
                                            id="nib"
                                            value={data.nib ?? ''}
                                            onChange={(e) => setData('nib', e.target.value)}
                                            placeholder="Masukkan nomor NIB"
                                            className={cn(
                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                            )}
                                        />
                                    </div>
                                </div>
                                <div className="col-span-3 mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                                    <h1 className="text-xl font-semibold">Data Direktur</h1>
                                    <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div className="w-full">
                                            <Label htmlFor="nama_pj">Nama</Label>
                                            <Input
                                                id="nama_pj"
                                                value={data.nama_pj}
                                                onChange={(e) => setData('nama_pj', e.target.value)}
                                                placeholder="Masukkan Nama"
                                            />
                                        </div>
                                        <div className="w-full">
                                            <Label htmlFor="no_ktp_pj">Nik Direktur</Label>
                                            <NumericFormat
                                                id="no_ktp_pj"
                                                value={data.no_ktp_pj}
                                                onChange={(e) => setData('no_ktp_pj', e.target.value)}
                                                className={cn(
                                                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                                )}
                                                placeholder="Enter Nik Direktur"
                                                allowNegative={false}
                                                decimalScale={0}
                                            />
                                        </div>
                                        <div className="w-full">
                                            <Label htmlFor="no_telp_pj">No. Telp. Direktur</Label>
                                            <PhoneInput
                                                defaultCountry="id"
                                                value={data.no_telp_pj?.toString() || ''}
                                                onChange={(phone) => setData('no_telp_pj', phone)}
                                                inputClassName={cn(
                                                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                                    'bg-white dark:bg-black',
                                                )}
                                                placeholder="Enter No. Telp. Direktur"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="col-span-3 mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                                    <h1 className="text-xl font-semibold">Data PIC</h1>
                                    <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div className="w-full">
                                            <Label htmlFor="nama_personal">
                                                Nama <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="nama_personal"
                                                value={data.nama_personal}
                                                onChange={(e) => setData('nama_personal', e.target.value)}
                                                placeholder="Masukkan nama personal"
                                            />
                                        </div>
                                        <div className="w-full">
                                            <Label htmlFor="jabatan_personal">
                                                Jabatan <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="jabatan_personal"
                                                value={data.jabatan_personal}
                                                onChange={(e) => setData('jabatan_personal', e.target.value)}
                                                placeholder="Masukkan jabatan personal"
                                            />
                                        </div>
                                        <div className="w-full space-y-2">
                                            <Label>
                                                No. Telp. <span className="text-red-500">*</span>
                                            </Label>
                                            {data.no_telp_personal.map((phone, idx) => (
                                                <div key={idx} className="flex items-center gap-2">
                                                    <div className="flex-1">
                                                        <PhoneInput
                                                            defaultCountry="id"
                                                            value={phone || ''}
                                                            onChange={(phoneVal) => {
                                                                const newPhones = [...data.no_telp_personal];
                                                                newPhones[idx] = phoneVal;
                                                                setData('no_telp_personal', newPhones);
                                                            }}
                                                            inputClassName={cn(
                                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                                            )}
                                                            placeholder="Masukkan no. telp personal"
                                                        />
                                                    </div>
                                                    {data.no_telp_personal.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            className="h-9 w-9 shrink-0 border-red-200 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 dark:border-transparent dark:bg-destructive dark:text-destructive-foreground dark:hover:bg-destructive/90"
                                                            onClick={() => {
                                                                const newPhones = data.no_telp_personal.filter((_, i) => i !== idx);
                                                                setData('no_telp_personal', newPhones);
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            ))}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="mt-1"
                                                onClick={() => setData('no_telp_personal', [...data.no_telp_personal, ''])}
                                            >
                                                <Plus className="mr-1.5 h-3.5 w-3.5" /> Tambah Nomor
                                            </Button>
                                        </div>
                                        <div className="w-full">
                                            <Label htmlFor="email_personal">
                                                Email <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="email_personal"
                                                value={data.email_personal}
                                                onChange={(e) => setData('email_personal', e.target.value)}
                                                placeholder="Masukkan email personal"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="col-span-3 mt-4">
                                    <h1 className="mb-2 text-xl font-semibold">Lampiran</h1>

                                    <div className="col-span-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        {/* NPWP */}
                                        <div className="w-full">
                                            <ResettableDropzone
                                                label="Upload NPWP"
                                                isRequired={rules.is_npwp}
                                                uploadConfig={{
                                                    url: '/customer/upload-temp', // Endpoint upload raw
                                                    payload: {
                                                        type: 'npwp',
                                                        npwp_number: data.no_npwp,
                                                        id_perusahaan: id_perusahaan,
                                                        order: '1',
                                                    },
                                                }}
                                                onFileChange={(file, response) => handleUploadSuccess('npwp', file, response)}
                                                existingFile={customer?.attachments?.find((a) => a.type === 'npwp')}
                                            />
                                            {rules.is_npwp && <p className="mt-1 text-xs text-red-500">* Wajib unggah NPWP dalam format PDF</p>}
                                        </div>

                                        {/* NIB */}
                                        <div className="w-full">
                                            <ResettableDropzone
                                                label="Upload NIB"
                                                isRequired={rules.is_nib && data.bentuk_badan_usaha !== 'Customer Perorangan'}
                                                uploadConfig={{
                                                    url: '/customer/upload-temp',
                                                    payload: {
                                                        type: 'nib',
                                                        npwp_number: data.no_npwp,
                                                        id_perusahaan: id_perusahaan,
                                                        order: '2',
                                                    },
                                                }}
                                                onFileChange={(file, response) => handleUploadSuccess('nib', file, response)}
                                                existingFile={customer?.attachments?.find((a) => a.type === 'nib')}
                                            />
                                            {rules.is_nib && data.bentuk_badan_usaha !== 'Customer Perorangan' && (
                                                <p className="mt-1 text-xs text-red-500">* Wajib unggah NIB dalam format PDF</p>
                                            )}
                                        </div>

                                        {/* SPTKP */}
                                        <div className="w-full">
                                            <ResettableDropzone
                                                label="Upload SPTKP"
                                                isRequired={rules.is_sptkp}
                                                uploadConfig={{
                                                    url: '/customer/upload-temp',
                                                    payload: {
                                                        type: 'sppkp',
                                                        npwp_number: data.no_npwp,
                                                        id_perusahaan: id_perusahaan,
                                                        order: '3',
                                                    },
                                                }}
                                                onFileChange={(file, response) => handleUploadSuccess('sppkp', file, response)}
                                                existingFile={customer?.attachments?.find((a) => a.type === 'sppkp')}
                                            />
                                            {rules.is_sptkp && <p className="mt-1 text-xs text-red-500">* Wajib unggah SPTKP dalam format PDF</p>}
                                        </div>

                                        {/* KTP */}
                                        <div className="w-full">
                                            <ResettableDropzone
                                                label="Upload KTP"
                                                isRequired={rules.is_ktp}
                                                uploadConfig={{
                                                    url: '/customer/upload-temp',
                                                    payload: {
                                                        type: 'ktp',
                                                        npwp_number: data.no_npwp,
                                                        id_perusahaan: id_perusahaan,
                                                        order: '4',
                                                    },
                                                }}
                                                onFileChange={(file, response) => handleUploadSuccess('ktp', file, response)}
                                                existingFile={customer?.attachments?.find((a) => a.type === 'ktp')}
                                            />
                                            {rules.is_ktp && <p className="mt-1 text-xs text-red-500">* Wajib unggah KTP dalam format PDF</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="col-span-3">
                                    <div className="w-full">
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            Diisi tanggal{' '}
                                            <strong>
                                                {new Date().toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'long',
                                                    year: 'numeric',
                                                })}
                                            </strong>{' '}
                                            <strong> oleh {data.nama_personal || ''}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4 flex gap-2">
                                <Button type="submit" disabled={isLoading || processing}>
                                    {isLoading ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            {customer ? 'Saving...' : 'Creating...'}
                                        </>
                                    ) : customer ? (
                                        'Save'
                                    ) : (
                                        'Create'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <Dialog open={isConfirmDialogOpen} onOpenChange={setIsConfirmDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Konfirmasi Data Customer</DialogTitle>
                        <DialogDescription>
                            Pastikan semua data yang Anda isi sudah benar. Jika masih ada data yang salah, silakan kembali dan perbaiki terlebih
                            dahulu.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] space-y-4 overflow-y-auto rounded-md border p-4 text-sm">
                        <div>
                            <h3 className="mb-2 text-2xl font-semibold">Data Perusahaan</h3>
                            <div className="space-y-2">
                                <p>
                                    <strong>Kategori Usaha :</strong> {data.kategori_usaha}
                                </p>
                                <p>
                                    <strong>Nama Perusahaan :</strong> {data.nama_perusahaan}
                                </p>
                                <p>
                                    <strong>Bentuk Badan Usaha :</strong> {data.bentuk_badan_usaha}
                                </p>
                                <p>
                                    <strong>Alamat Lengkap :</strong> {data.alamat_lengkap}
                                </p>
                                <p>
                                    <strong>Kota :</strong> {data.kota}
                                </p>
                                <p>
                                    <strong>No Telp Perusahaan :</strong> {data.no_telp}
                                </p>
                                <p>
                                    <strong>No Fax :</strong> {data.no_fax || '-'}
                                </p>
                                <p>
                                    <strong>Alamat Penagihan :</strong> {data.alamat_penagihan}
                                </p>
                                <p>
                                    <strong>Email Perusahaan :</strong> {data.email}
                                </p>
                                <p>
                                    <strong>Website :</strong> {data.website || '-'}
                                </p>
                                <p>
                                    <strong>Terms of Payment :</strong> {data.top}
                                </p>
                                <p>
                                    <strong>Status Perpajakan :</strong> {data.status_perpajakan}
                                </p>
                                <p>
                                    <strong>No NPWP :</strong> {data.no_npwp}
                                </p>
                                <p>
                                    <strong>No NPWP 16 :</strong> {data.no_npwp_16}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h3 className="mt-3 mb-2 text-2xl font-semibold">Data Direktur</h3>
                            <div className="space-y-2">
                                <p>
                                    <strong>Nama Direktur :</strong> {data.nama_pj}
                                </p>
                                <p>
                                    <strong>NIK Direktur :</strong> {data.no_ktp_pj}
                                </p>
                                <p>
                                    <strong>No Telp Direktur :</strong> {normalizePhone(data.no_telp_pj)}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h3 className="mt-3 mb-2 text-2xl font-semibold">Data Personal</h3>
                            <div className="space-y-2">
                                <p>
                                    <strong>Nama Personal :</strong> {data.nama_personal}
                                </p>
                                <p>
                                    <strong>Jabatan Personal :</strong> {data.jabatan_personal}
                                </p>
                                <p>
                                    <strong>No Telp Personal :</strong> {data.no_telp_personal}
                                </p>
                                <p>
                                    <strong>Email Personal :</strong> {data.email_personal}
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setIsConfirmDialogOpen(false)}>
                            Kembali
                        </Button>

                        <Button type="button" onClick={submitFinalData} disabled={isLoading || processing}>
                            Saya Setuju
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
