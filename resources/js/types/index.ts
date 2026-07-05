/* eslint-disable @typescript-eslint/no-explicit-any */
// import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export type NavItem = {
    title: string;
    url: string;
    icon: React.ElementType;
};

export type MainNavItem = NavItem & {
    permissions?: string[];
    subItems?: (NavItem & { permissions?: string[] })[];
};

export type UserRole = {
    name: string;
};

export type PageProps = {
    auth: {
        user: {
            id: number;
            name: string;
            roles: UserRole[];
            permissions: string[];
        };
    };
};

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    all_companies: { id: number; nama_perusahaan: string }[];
    admin_active_company_id: number | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    uid: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
    roles: { name: string }[];
    companies: { id: number; nama_perusahaan: string }[];
}

export interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

export interface Permission {
    id: number;
    name: string;
}

export interface Perusahaan {
    id: number;
    nama_perusahaan: string;
    id_User_1: number | null;
    id_User_2: number | null;
    id_User_3: number | null;
    id_User: number | null;
    Notify_1: boolean | null;
    Notify_2: boolean | null;
    is_ppjk?: boolean;
    created_at?: string;
    updated_at?: string;
    user?: User;
    logo_url?: string | null;
    domain?: {
        id?: number;
        domain?: string | null;
        path_company_logo?: string | null;
    } | null;
    [key: string]: unknown;
}

export type Payment = {
    id: string;
    amount: number;
    status: string | 'pending' | 'failed' | 'processing' | 'success';
    email: string;
};

export type MasterCustomer = {
    id_user: any;
    creator_role: string;
    id: number | null;
    id_perusahaan?: any;
    kategori_usaha: string;
    nama_perusahaan: string;
    bentuk_badan_usaha: string;
    alamat_lengkap: string;
    kota: string;
    no_telp: string;
    no_fax: number | null | string;
    alamat_penagihan: string;
    email: string;
    website: string;
    top: string;
    status_perpajakan: string;
    no_npwp: string | null;
    no_npwp_16: string | null;
    nib?: string | null;
    jenis_perusahaan?: string | null;
    nama_pj: string;
    no_ktp_pj: string;
    no_telp_pj: string;
    nama_personal: string;
    jabatan_personal: string;
    no_telp_personal: string;
    email_personal: string;
    keterangan_reject: string | null;
    user_id: number;
    approved_1_by: number | null;
    approved_2_by: number | null;
    rejected_1_by: number | null;
    rejected_2_by: number | null;
    keterangan: string | null;
    tgl_approval_1: Date | null;
    tgl_approval_2: Date | null;
    tgl_customer: Date | null;
    attachments: Attachment[];
};

export type Attachment = {
    id: number;
    customer_id: number;
    nama_file: string;
    path: string;
    type: 'npwp' | 'nib' | 'sppkp' | 'ktp' | 'note';
};

export type DropzoneFileStatus = {
    id: string;
    fileName: string;
    file: File;
    tries: number;
    status: 'success';
    result: string;
};

export type AttachmentType = 'npwp' | 'nib' | 'sppkp' | 'ktp' | 'note';

export type BankCustomer = {
    id: number;
    nama_perusahaan: string;
    kategori_usaha: string;
    bentuk_badan_usaha: string;
    kota: string;
    no_telp: string;
    npwp: string;
    npwp_16: string;
    nib: string;
    pic: string;
    jabatan_pic: string;
    no_telp_pic: string;
    email_pic: string;
    entitas: 'Lengkap' | 'Belum Lengkap';
    nama_perusahaan_induk: string;
};

export type MasterSupplier = {
    id_user: any;
    creator_role: string;
    id: number | null;
    id_perusahaan?: any;
    supplier_category: string;
    kategori_usaha: string;
    nama_perusahaan: string;
    bentuk_badan_usaha: string;
    alamat_lengkap: string;
    kota: string;
    no_telp: string;
    no_fax: number | null | string;
    alamat_penagihan: string;
    email: string;
    website: string;
    top: string;
    status_perpajakan: string;
    no_npwp: string | null;
    no_npwp_16: string | null;
    nib?: string | null;
    jenis_perusahaan?: string | null;
    nama_pj: string;
    no_ktp_pj: string;
    no_telp_pj: string;
    nama_personal: string;
    jabatan_personal: string;
    no_telp_personal: string;
    email_personal: string;
    keterangan_reject: string | null;
    user_id: number;
    approved_1_by: number | null;
    approved_2_by: number | null;
    rejected_1_by: number | null;
    rejected_2_by: number | null;
    keterangan: string | null;
    tgl_approval_1: Date | null;
    tgl_approval_2: Date | null;
    tgl_supplier: Date | null;
    attachments: SupplierAttachment[];
};

export type SupplierAttachment = {
    id: number;
    supplier_id: number;
    nama_file: string;
    path: string;
    type: 'npwp' | 'nib' | 'sppkp' | 'ktp' | 'note';
};

export type BankSupplier = {
    id: number;
    supplier_category: string;
    nama_perusahaan: string;
    kategori_usaha: string;
    bentuk_badan_usaha: string;
    kota: string;
    no_telp: string;
    npwp: string;
    npwp_16: string;
    nib: string;
    pic: string;
    jabatan_pic: string;
    no_telp_pic: string;
    email_pic: string;
    entitas: 'Lengkap' | 'Belum Lengkap';
    nama_perusahaan_induk: string;
};
