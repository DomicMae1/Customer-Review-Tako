/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable @typescript-eslint/no-unused-vars */
import { ResettableDropzone } from '@/components/ResettableDropzone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2 } from 'lucide-react';
import Swal from 'sweetalert2';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Attachment, AttachmentType, Auth, MasterCustomer } from '@/types';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { File } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';
import { NumericFormat } from 'react-number-format';
import { toast } from 'sonner';

export default function CustomerForm({
    auth,
    customer,
    onSuccess,
}: {
    auth: Auth;
    customer?: MasterCustomer;
    attachment?: Attachment;
    onSuccess?: () => void;
}) {
    const { data, setData, processing, errors } = useForm<MasterCustomer>({
        id: customer?.id || null,
        id_perusahaan: customer?.id_perusahaan ? String(customer.id_perusahaan) : '',
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
        user_id: customer?.user_id || auth.user.id,
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

    const [npwpFileStatuses, setNpwpFileStatuses] = useState<any[]>([]);

    const [nibFileStatuses, setNibFileStatuses] = useState<any[]>([]);

    const [sppkpFileStatuses, setSppkpFileStatuses] = useState<any[]>([]);

    const [ktpFileStatuses, setKtpFileStatuses] = useState<any[]>([]);

    const [npwpAttachment, setNpwpAttachment] = useState<Attachment | null>(null);
    const [nibAttachment, setNibAttachment] = useState<Attachment | null>(null);
    const [sppkpAttachment, setSppkpAttachment] = useState<Attachment | null>(null);
    const [ktpAttachment, setKtpAttachment] = useState<Attachment | null>(null);

    const handleUploadSuccess = (type: string, file: File | null, response: any) => {
        if (file && response) {
            // Simpan data dari response backend (path temp, nama file)
            const newAttachment: Attachment = {
                id: 0, // ID dummy
                customer_id: 0,
                nama_file: response.nama_file,
                path: response.path, // Ini path 'temp/...'
                type: type,
                // Opsional: simpan mode kompresi jika ingin dikirim balik
                mode: 'medium',
            };

            if (type === 'npwp') setNpwpAttachment(newAttachment);
            if (type === 'nib') setNibAttachment(newAttachment);
            if (type === 'sppkp') setSppkpAttachment(newAttachment);
            if (type === 'ktp') setKtpAttachment(newAttachment);
        } else {
            // Reset jika user menghapus file
            if (type === 'npwp') setNpwpAttachment(null);
            if (type === 'nib') setNibAttachment(null);
            if (type === 'sppkp') setSppkpAttachment(null);
            if (type === 'ktp') setKtpAttachment(null);
        }
    };
    // const [isModalOpen, setIsModalOpen] = useState(false);

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

    useEffect(() => {
        if (!customer) return;

        const setStatus = (attachment: any | undefined, setState: (s: any[]) => void, type: string) => {
            if (attachment && attachment.path && !attachment.path.startsWith('blob:')) {
                setState([
                    {
                        id: `existing-${type}`,
                        status: 'success',
                        fileName: attachment.nama_file,
                        result: attachment.path,
                    },
                ]);
            }
        };

        setStatus(
            customer.attachments?.find((a) => a.type === 'npwp'),
            setNpwpFileStatuses,
            'npwp',
        );
        setStatus(
            customer.attachments?.find((a) => a.type === 'nib'),
            setNibFileStatuses,
            'nib',
        );
        setStatus(
            customer.attachments?.find((a) => a.type === 'sppkp'),
            setSppkpFileStatuses,
            'sppkp',
        );
        setStatus(
            customer.attachments?.find((a) => a.type === 'ktp'),
            setKtpFileStatuses,
            'ktp',
        );
    }, [customer]);

    const handleSubmit: FormEventHandler = async (e) => {
        e.preventDefault();

        const newErrors: typeof errors_kategori = {};

        setIsLoading(true);
        const showValidationError = (field: keyof typeof errors_kategori, message: string) => {
            setErrors((prev) => ({ ...prev, [field]: message }));
            toast.error(message);
            setIsLoading(false);
        };

        const normalizePhone = (phone?: string | null) => {
            if (!phone) return null;

            const cleaned = phone.replace(/\s/g, '');

            if (cleaned === '+62' || cleaned === '+') {
                return null;
            }

            return phone;
        };

        const isCustomerPerorangan = data.bentuk_badan_usaha === 'Customer Perorangan';
        const isManagerOrDirektur = auth.user?.roles?.some((role: { name: string }) => ['manager', 'direktur'].includes(role.name));

        if (!customer?.id) {
            try {
                const res = await axios.post(route('customer.check-npwp'), {
                    no_npwp: data.no_npwp,
                    no_npwp_16: data.no_npwp_16,
                });

                const { exists, nama_perusahaan, lawyer_rejected, note, lawyer_file, auditor_note, auditor_note_text, auditor_file } = res.data;

                if (exists) {
                    let htmlContent = `<div style="text-align: left; font-size: 14px; color: #333;">`;
                    htmlContent += `<div style="margin-bottom: 15px;">
                                        Npwp/Customer ini sudah terdaftar di perusahaan <b>${nama_perusahaan}</b>.
                                    </div>`;

                    // ... (Bagian HTML Lawyer & Auditor Anda TETAP SAMA, disingkat agar rapi) ...
                    if (lawyer_rejected) {
                        htmlContent += `<div style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                                            <b>Catatan Lawyer:</b><br/> "${note ?? '-'}"
                                            ${lawyer_file ? `<br/><a href="${lawyer_file}" target="_blank" style="color:blue">📄 Lampiran Lawyer</a>` : ''}
                                        </div>`;
                    }
                    if (auditor_note) {
                        htmlContent += `<div>
                                            <b>Catatan Auditor:</b><br/> "${auditor_note_text ?? '-'}"
                                            ${auditor_file ? `<br/><a href="${auditor_file}" target="_blank" style="color:blue">📄 Lampiran Auditor</a>` : ''}
                                        </div>`;
                    }
                    htmlContent += `<div style="margin-top: 20px; font-weight: bold; color: #d33; text-align: center;">Apakah anda yakin ingin menambahkan?</div></div>`;

                    const result = await Swal.fire({
                        position: 'top',
                        html: htmlContent,
                        width: '650px',
                        showCancelButton: true,
                        confirmButtonText: 'Simpan',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                    });

                    if (!result.isConfirmed) {
                        setIsLoading(false);
                        return; // Stop jika user batal
                    }
                }
            } catch (error) {
                console.error('Error checking NPWP:', error);
                // Opsional: Handle error connection
            }
        }

        if (isManagerOrDirektur && !data.id_perusahaan) {
            toast.error('Perusahaan wajib dipilih');
            setIsLoading(false);
            return;
        }

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

        if (!data.no_telp || data.no_telp.trim().length <= 3) {
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

        // Validasi panjang NPWP 16 digit
        const npwp16OnlyNumber = data.no_npwp_16.replace(/\D/g, '');

        if (npwp16OnlyNumber.length < 16) {
            showValidationError('no_npwp_16', 'Nomer NPWP 16 belum lengkap');
            return;
        }

        if (!data.nama_personal || !data.nama_personal.trim()) {
            showValidationError('nama_personal', 'Nama Personal wajib diisi');
            return;
        }

        if (!data.jabatan_personal || !data.jabatan_personal.trim()) {
            showValidationError('jabatan_personal', 'Jabatan Personal wajib diisi');
            return;
        }

        if (!data.no_telp_personal || data.no_telp_personal.trim().length <= 3) {
            showValidationError('no_telp_personal', 'No Telp Personal wajib diisi');
            return;
        }

        if (!data.email_personal || !data.email_personal.trim()) {
            showValidationError('email_personal', 'Email Personal wajib diisi');
            return;
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            toast.error('Mohon lengkapi data formulir.');
            setIsLoading(false);
            return;
        }

        const getFinalAttachment = (newFile: Attachment | null, type: AttachmentType): Attachment | null => {
            if (newFile) return newFile; // File baru dari state
            const existing = customer?.attachments?.find((a) => a.type === type); // File lama dari props
            if (existing) return existing;
            return null;
        };

        const finalNpwp = getFinalAttachment(npwpAttachment, 'npwp');
        const finalNib = getFinalAttachment(nibAttachment, 'nib');
        const finalSppkp = getFinalAttachment(sppkpAttachment, 'sppkp');
        const finalKtp = getFinalAttachment(ktpAttachment, 'ktp');

        // Validasi Ketersediaan File Wajib
        if (!finalNpwp) {
            toast.error('Dokumen NPWP wajib diunggah.');
            setIsLoading(false);
            return;
        }
        if (!isCustomerPerorangan && !finalNib) {
            toast.error('Dokumen NIB wajib diunggah.');
            setIsLoading(false);
            return;
        }
        if (!finalKtp) {
            toast.error('Dokumen KTP wajib diunggah.');
            setIsLoading(false);
            return;
        }

        const rawAttachments = [finalNpwp, isCustomerPerorangan ? null : finalNib, finalSppkp, finalKtp].filter(Boolean) as Attachment[];
        const processedAttachments: any[] = [];

        try {
            const processResults = await Promise.all(
                rawAttachments.map(async (att, index) => {
                    // Cek: Apakah path diawali 'temp/'? (Artinya file baru upload)
                    if (att.path.startsWith('temp/')) {
                        // Panggil API process-attachment
                        const processRes = await axios.post(route('customer.process-attachment'), {
                            path: att.path,
                            nama_file: att.nama_file,
                            id_perusahaan: auth.user.id_perusahaan,
                            mode: 'medium', // Mode kompresi default

                            type: att.type,
                            npwp_number: data.no_npwp,
                            customer_id: customer?.id,
                            increment_order: index + 1,
                        });

                        // Kembalikan object attachment dengan PATH BARU (Final Path)
                        return {
                            ...att,
                            path: processRes.data.final_path,
                            nama_file: processRes.data.nama_file,
                        };
                    } else {
                        // Jika file lama (tidak di temp), kembalikan apa adanya
                        return att;
                    }
                }),
            );

            // Simpan hasil proses ke array final
            processedAttachments.push(...processResults);
        } catch (processError) {
            console.error('Gagal memproses dokumen:', processError);
            toast.error('Gagal memproses/mengompres dokumen. Silakan coba lagi.');
            setIsLoading(false);
            return; // Hentikan proses submit jika gagal kompres
        }

        const finalPayload = {
            ...data,
            no_telp_pj: normalizePhone(data.no_telp_pj),
            id_perusahaan: Number(data.id_perusahaan),
            attachments: processedAttachments,
        };

        if (customer?.id) {
            // UPDATE DATA
            router.put(route('customer.update', customer.id), finalPayload, {
                onSuccess: () => {
                    toast.success('Data berhasil diperbarui!');
                    onSuccess?.(); // Callback jika ada (misal tutup modal)
                    setIsLoading(false);
                },
                onError: (errors) => {
                    toast.error('Update error:', errors);
                    setIsLoading(false);
                },
            });
        } else {
            // CREATE DATA BARU
            router.post(route('customer.store'), finalPayload, {
                onSuccess: () => {
                    toast.success('Data berhasil disimpan!');
                    setIsLoading(false);
                },
                onError: (errors) => {
                    toast.error('Store error:', errors);
                    setIsLoading(false);
                },
            });
        }
    };

    return (
        <div className="rounded-2xl p-4">
            <h1 className="mb-4 text-3xl font-semibold">{customer ? 'Edit Data Customer' : 'Buat Data Customer'}</h1>
            <form onSubmit={handleSubmit}>
                <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div className="col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {auth.user?.roles?.some((role: { name: string }) => ['manager', 'direktur'].includes(role.name)) && (
                            <div className="w-full grid-cols-1 md:w-1/2 md:grid-cols-2 lg:col-span-3 lg:w-1/3">
                                <Label htmlFor="id_perusahaan">
                                    Perusahaan <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={data.id_perusahaan ? String(data.id_perusahaan) : ''}
                                    onValueChange={(value) => {
                                        setData('id_perusahaan', value);
                                        setErrors((prev) => ({ ...prev, id_perusahaan: undefined }));
                                    }}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih Perusahaan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {auth.user?.companies?.map((perusahaan) => (
                                            <SelectItem key={perusahaan.id} value={String(perusahaan.id)}>
                                                {perusahaan.nama_perusahaan}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

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
                            <Input id="kota" value={data.kota} onChange={(e) => setData('kota', e.target.value)} placeholder="Masukkan Kota" />
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
                                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                )}
                                placeholder="Enter No. Perusahaan"
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
                                    'aria-invalid:ring-destructive/20 aria-invalid:border-destructive',
                                )}
                                placeholder="Masukkan nomor fax (optional)"
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
                                onChange={(e) => {
                                    setData('no_npwp', formatNpwp(e.target.value));
                                }}
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
                    <div className="col-span-3 mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
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
                                    placeholder="Masukkan Nik Direktur"
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
                                        'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                                    )}
                                    placeholder="Masukkan No. Telp. Direktur"
                                />
                            </div>
                        </div>
                    </div>
                    <div className="col-span-3 mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
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
                            <div className="w-full">
                                <Label htmlFor="no_telp_personal">
                                    No. Telp. <span className="text-red-500">*</span>
                                </Label>
                                <PhoneInput
                                    defaultCountry="id"
                                    value={data.no_telp_personal?.toString() || ''}
                                    onChange={(phone) => setData('no_telp_personal', phone)}
                                    inputClassName={cn(
                                        'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
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
                        <h1 className="mb-2 text-xl font-semibold">
                            Lampiran <span className="text-sm font-normal italic">(maksimal ukuran attachment 5 mb)</span>
                        </h1>

                        {/* 4 Dropzone Kolom */}
                        <div className="col-span-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            {/* NPWP Upload */}
                            <div className="w-full">
                                <ResettableDropzone
                                    label="Upload NPWP"
                                    isRequired={true}
                                    // Kirim config untuk Auto Upload
                                    uploadConfig={{
                                        url: '/customer/upload-temp',
                                        payload: {
                                            type: 'npwp',
                                            npwp_number: data.no_npwp, // Pastikan ini tidak kosong
                                            id_perusahaan: auth.user.id_perusahaan,
                                            order: '1',
                                            mode: 'medium', // Opsi kompresi
                                        },
                                    }}
                                    // Tangkap hasil response
                                    onFileChange={(file, response) => handleUploadSuccess('npwp', file, response)}
                                    // Tampilkan file jika sudah ada (baik dari DB atau baru diupload)
                                    existingFile={npwpAttachment || customer?.attachments?.find((a) => a.type === 'npwp')}
                                />
                                <p className="mt-1 text-xs text-red-500">* Wajib unggah NPWP dalam format PDF</p>
                            </div>

                            {/* NIB Upload */}
                            <div className="w-full">
                                <ResettableDropzone
                                    label="Upload NIB"
                                    isRequired={data.bentuk_badan_usaha !== 'Customer Perorangan'}
                                    uploadConfig={{
                                        url: '/customer/upload-temp',
                                        payload: {
                                            type: 'nib',
                                            npwp_number: data.no_npwp,
                                            id_perusahaan: auth.user.id_perusahaan,
                                            order: '2',
                                        },
                                    }}
                                    onFileChange={(file, response) => handleUploadSuccess('nib', file, response)}
                                    existingFile={nibAttachment || customer?.attachments?.find((a) => a.type === 'nib')}
                                />
                                <p className="mt-1 text-xs text-red-500">
                                    {data.bentuk_badan_usaha === 'Customer Perorangan' ? '' : '* Wajib unggah NIB dalam format PDF'}
                                </p>
                            </div>

                            {/* SPPKP Upload */}
                            <div className="w-full">
                                <ResettableDropzone
                                    label="Upload SPTKP"
                                    uploadConfig={{
                                        url: '/customer/upload-temp',
                                        payload: {
                                            type: 'sppkp',
                                            npwp_number: data.no_npwp,
                                            id_perusahaan: auth.user.id_perusahaan,
                                            order: '3',
                                        },
                                    }}
                                    onFileChange={(file, response) => handleUploadSuccess('sppkp', file, response)}
                                    existingFile={sppkpAttachment || customer?.attachments?.find((a) => a.type === 'sppkp')}
                                />
                            </div>

                            {/* KTP Upload */}
                            <div className="w-full">
                                <ResettableDropzone
                                    label="Upload KTP"
                                    isRequired={true}
                                    uploadConfig={{
                                        url: '/customer/upload-temp',
                                        payload: {
                                            type: 'ktp',
                                            npwp_number: data.no_npwp,
                                            id_perusahaan: auth.user.id_perusahaan,
                                            order: '4',
                                        },
                                    }}
                                    onFileChange={(file, response) => handleUploadSuccess('ktp', file, response)}
                                    existingFile={ktpAttachment || customer?.attachments?.find((a) => a.type === 'ktp')}
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
                                <strong> oleh {data.nama_personal || '...'}</strong>
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

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.visit('/customer')}
                        disabled={isLoading}
                        className="border border-gray-600"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    );
}
