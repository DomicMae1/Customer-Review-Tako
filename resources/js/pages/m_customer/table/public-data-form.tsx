/* eslint-disable @typescript-eslint/no-unused-vars */
import { ResettableDropzone } from '@/components/ResettableDropzone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Attachment, AttachmentType, MasterCustomer } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { File, Loader2, Moon, Sun } from 'lucide-react';
// import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
// import { AlertCircle } from "lucide-react"
import axios from 'axios';
import { FormEventHandler, useEffect, useState } from 'react';
import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';
import { NumericFormat } from 'react-number-format';

export default function PublicCustomerForm({
    customer,
    onSuccess,
    isFilled = false,
}: {
    customer?: MasterCustomer;
    onSuccess?: () => void;
    isFilled?: boolean;
}) {
    const { customer_name, token, user_id, id_perusahaan } = usePage().props as unknown as {
        customer_name: string;
        token: string;
        user_id: number;
        id_perusahaan: number;
    };

    const { data, setData, processing, errors } = useForm<MasterCustomer>({
        id: customer?.id || null,
        kategori_usaha: customer?.kategori_usaha || '',
        nama_perusahaan: customer?.nama_perusahaan || '',
        bentuk_badan_usaha: customer?.bentuk_badan_usaha || '',
        alamat_lengkap: customer?.alamat_lengkap || '',
        kota: customer?.kota || '',
        no_telp: customer?.no_telp ?? null,
        no_fax: customer?.no_fax ?? null,
        alamat_penagihan: customer?.alamat_penagihan || '',
        email: customer?.email || '',
        website: customer?.website || '',
        top: customer?.top || '',
        status_perpajakan: customer?.status_perpajakan || '',
        no_npwp: customer?.no_npwp || '',
        no_npwp_16: customer?.no_npwp_16 || '',
        nama_pj: customer?.nama_pj || '',
        no_ktp_pj: customer?.no_ktp_pj || '',
        no_telp_pj: customer?.no_telp_pj || '',
        nama_personal: customer?.nama_personal || '',
        jabatan_personal: customer?.jabatan_personal || '',
        no_telp_personal: customer?.no_telp_personal || '',
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

    const [lainKategori, setLainKategori] = useState('');
    const [showLainKategori, setShowLainKategori] = useState(false);

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
        nama_pj?: string;
        no_ktp_pj?: string;
        no_telp_pj?: string;
        nama_personal?: string;
        jabatan_personal?: string;
        no_telp_personal?: string;
        email_personal?: string;
        attachments?: string;
    }>({});

    const [npwpFile, setNpwpFile] = useState<File | null>(null);
    const [nibFile, setNibFile] = useState<File | null>(null);
    const [sppkpFile, setSppkpFile] = useState<File | null>(null);
    const [ktpFile, setKtpFile] = useState<File | null>(null);
    // const [isModalOpen, setIsModalOpen] = useState(false);

    useEffect(() => {
        if (isFilled) {
            alert('Form sudah diisi sebelumnya. Kamu tidak bisa mengedit data ini lagi.');
            router.visit('/');
        }
    }, [isFilled]);

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

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();

        const newErrors: typeof errors_kategori = {};

        setIsLoading(true);

        if (!data.kategori_usaha) {
            const message = 'Kategori usaha wajib dipilih';
            setErrors((prev) => ({ ...prev, kategori_usaha: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (data.kategori_usaha === 'lain2' && !lainKategori.trim()) {
            const message = 'Kategori lainnya wajib diisi';
            setErrors((prev) => ({ ...prev, lain_kategori: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.nama_perusahaan) {
            const message = 'Nama Perusahaan wajib diisi';
            setErrors((prev) => ({ ...prev, nama_perusahaan: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.bentuk_badan_usaha) {
            const message = 'Bentuk badan usaha wajib dipilih';
            setErrors((prev) => ({ ...prev, bentuk_badan_usaha: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.alamat_lengkap || !data.alamat_lengkap.trim()) {
            const message = 'Alamat lengkap wajib diisi';
            setErrors((prev) => ({ ...prev, alamat_lengkap: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.kota || !data.kota.trim()) {
            const message = 'Kota wajib diisi';
            setErrors((prev) => ({ ...prev, kota: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_telp || data.no_telp.trim().length <= 3) {
            const message = 'No Telpon Perusahaan wajib diisi';
            setErrors((prev) => ({ ...prev, no_telp: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.alamat_penagihan || !data.alamat_penagihan.trim()) {
            const message = 'Alamat Perusahaan wajib diisi';
            setErrors((prev) => ({ ...prev, alamat_penagihan: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.email || !data.email.trim()) {
            const message = 'Email Perusahaan wajib diisi';
            setErrors((prev) => ({ ...prev, email: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.top || !data.top.trim()) {
            const message = 'Term of Payment wajib diisi';
            setErrors((prev) => ({ ...prev, top: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.status_perpajakan) {
            const message = 'Status perpajakan wajib dipilih';
            setErrors((prev) => ({ ...prev, status_perpajakan: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_npwp || !data.no_npwp.trim()) {
            const message = 'Nomer NPWP wajib diisi';
            setErrors((prev) => ({ ...prev, no_npwp: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_npwp_16 || !data.no_npwp_16.trim()) {
            const message = 'Nomer NPWP 16 wajib diisi';
            setErrors((prev) => ({ ...prev, no_npwp_16: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.nama_pj || !data.nama_pj.trim()) {
            const message = 'Nama Direktur wajib diisi';
            setErrors((prev) => ({ ...prev, nama_pj: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_ktp_pj || !data.no_ktp_pj.trim()) {
            const message = 'NIK Direktur wajib diisi';
            setErrors((prev) => ({ ...prev, no_ktp_pj: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_telp_pj || data.no_telp_pj.trim().length <= 3) {
            const message = 'No Telp Direktur wajib diisi';
            setErrors((prev) => ({ ...prev, no_telp_pj: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.nama_personal || !data.nama_personal.trim()) {
            const message = 'Nama Personal wajib diisi';
            setErrors((prev) => ({ ...prev, nama_personal: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.jabatan_personal || !data.jabatan_personal.trim()) {
            const message = 'Jabatan Personal wajib diisi';
            setErrors((prev) => ({ ...prev, jabatan_personal: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.no_telp_personal || data.no_telp_personal.trim().length <= 3) {
            const message = 'No Telp Personal wajib diisi';
            setErrors((prev) => ({ ...prev, no_telp_personal: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (!data.email_personal || !data.email_personal.trim()) {
            const message = 'Email Personal wajib diisi';
            setErrors((prev) => ({ ...prev, email_personal: message }));
            alert(message);
            setIsLoading(false);
            return;
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        const hasExistingNpwp = customer?.attachments?.some((a) => a.type === 'npwp');
        if (!npwpFile && !hasExistingNpwp) {
            const message = 'Dokumen NPWP wajib diunggah.';
            alert(message);
            return;
        }

        const hasExistingNib = customer?.attachments?.some((a) => a.type === 'nib');
        if (!nibFile && !hasExistingNib) {
            const message = 'Dokumen NIB wajib diunggah.';
            alert(message);
            return;
        }

        const hasExistingKtp = customer?.attachments?.some((a) => a.type === 'ktp');
        if (!ktpFile && !hasExistingKtp) {
            const message = 'Dokumen KTP wajib diunggah.';
            alert(message);
            return;
        }

        setErrors(newErrors);
        setIsLoading(true);

        try {
            const uploadedAttachments: Attachment[] = [];

            if (npwpFile) {
                const npwp = await uploadAttachment(npwpFile, 'npwp', data.no_npwp);
                uploadedAttachments.push(npwp);
            }

            if (nibFile) {
                const nib = await uploadAttachment(nibFile, 'nib', data.no_npwp);
                uploadedAttachments.push(nib);
            }

            if (sppkpFile) {
                const sppkp = await uploadAttachment(sppkpFile, 'sppkp', data.no_npwp);
                uploadedAttachments.push(sppkp);
            }

            if (ktpFile) {
                const ktp = await uploadAttachment(ktpFile, 'ktp', data.no_npwp);
                uploadedAttachments.push(ktp);
            }

            const updatedAttachments = [...uploadedAttachments];

            const finalPayload = {
                ...data,
                attachments: updatedAttachments,
            };
            if (!customer || !customer.id) {
                try {
                    const res = await axios.post(route('customer.public.submit'), finalPayload);

                    window.alert(`✅ ${res.data.message}`);
                    setIsLoading(false);
                    window.location.reload();
                } catch (error) {
                    console.error(error);
                    setIsLoading(false);
                }
            }
        } catch (err) {
            console.error('Upload gagal:', err);
            alert('Gagal upload file. Silakan coba lagi.');
            setIsLoading(false);
        }
    };

    return (
        <>
            <Head title="Data Customer" />
            <div className="">
                <div className="mx-auto my-4 max-w-7xl rounded-2xl p-4 xl:my-16 xl:border">
                    <div className="flex items-center justify-between">
                        <h1 className="mb-4 text-3xl font-semibold">
                            {customer ? 'Edit Data Customer' : 'Data Customer'} ({customer_name})
                        </h1>

                        <Button variant="ghost" size="icon" onClick={toggleTheme} type="button">
                            {theme === 'light' ? <Moon className="h-6 w-6" /> : <Sun className="h-6 w-6" />}
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
                                            <SelectItem value="pma">Penanaman Modal Asing (PMA)</SelectItem>
                                            <SelectItem value="pmdn">Penanaman Modal Dalam Negeri (PMDN)</SelectItem>
                                            <SelectItem value="pt">Perseroan Terbatas (PT)</SelectItem>
                                            <SelectItem value="cv">Commanditaire Vennootschap (CV)</SelectItem>
                                            <SelectItem value="ud">Usaha Dagang (UD)</SelectItem>
                                            <SelectItem value="po">Perusahaan Perorangan (PO)</SelectItem>
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
                                <div className="w-full">
                                    <Label htmlFor="no_telp">
                                        Nomor Telp Perusahaan <span className="text-red-500">*</span>
                                    </Label>
                                    <PhoneInput
                                        defaultCountry="id"
                                        value={data.no_telp?.toString() || ''}
                                        onChange={(phone) => setData('no_telp', phone)}
                                        inputClassName={cn(
                                            'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                            'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                        )}
                                        placeholder="Masukkan nomor telepon"
                                    />
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
                                        Nomor NPWP <span className="text-red-500">*</span>
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
                                        Nomor NPWP (16 Digit) <span className="text-red-500">*</span>
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
                            </div>
                            <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <h1 className="mb-2 text-xl font-semibold">Data Direktur</h1>
                                <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <div className="w-full">
                                        <Label htmlFor="nama_pj">
                                            Nama <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="nama_pj"
                                            value={data.nama_pj}
                                            onChange={(e) => setData('nama_pj', e.target.value)}
                                            placeholder="Masukkan Terms of Payment"
                                        />
                                    </div>
                                    <div className="w-full">
                                        <Label htmlFor="no_ktp_pj">
                                            Nik Direktur <span className="text-red-500">*</span>
                                        </Label>
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
                                        <Label htmlFor="no_telp_pj">
                                            No. Telp. Direktur <span className="text-red-500">*</span>
                                        </Label>
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
                            <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <h1 className="mb-2 text-xl font-semibold">Data Personal</h1>
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
                                    <div className="w-full">
                                        <Label htmlFor="no_telp_personal">
                                            No. Telp. <span className="text-red-500">*</span>
                                        </Label>
                                        <PhoneInput
                                            defaultCountry="id"
                                            value={data.no_telp_personal?.toString() || ''}
                                            onChange={(phone) => setData('no_telp_personal', phone)}
                                            inputClassName={cn(
                                                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                            )}
                                            placeholder="Masukkan no. telp personal"
                                        />
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
                                    <div className="w-full">
                                        <ResettableDropzone
                                            label="Upload NPWP"
                                            isRequired={true}
                                            onFileChange={setNpwpFile}
                                            existingFile={customer?.attachments?.find((a) => a.type === 'npwp')}
                                        />
                                        <p className="mt-1 text-xs text-red-500">* Wajib unggah NPWP dalam format PDF</p>
                                    </div>
                                    <div className="w-full">
                                        <ResettableDropzone
                                            label="Upload NIB"
                                            isRequired={true}
                                            onFileChange={setNibFile}
                                            existingFile={customer?.attachments?.find((a) => a.type === 'nib')}
                                        />
                                        <p className="mt-1 text-xs text-red-500">* Wajib unggah NIB dalam format PDF</p>
                                    </div>
                                    <div className="w-full">
                                        <ResettableDropzone
                                            label="Upload SPPKP"
                                            onFileChange={setSppkpFile}
                                            existingFile={customer?.attachments?.find((a) => a.type === 'sppkp')}
                                        />
                                    </div>
                                    <div className="w-full">
                                        <ResettableDropzone
                                            label="Upload KTP"
                                            isRequired={true}
                                            onFileChange={setKtpFile}
                                            existingFile={customer?.attachments?.find((a) => a.type === 'ktp')}
                                        />
                                        <p className="mt-1 text-xs text-red-500">* Wajib unggah KTP dalam format PDF</p>
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
        </>
    );
}
