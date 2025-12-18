/* eslint-disable react-hooks/exhaustive-deps */
/* eslint-disable @typescript-eslint/no-explicit-any */
// resources/js/components/ResettableDropzone.tsx

import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { CloudUploadIcon, File as FileIcon, Loader2, Trash2Icon } from 'lucide-react';
import React, { useState } from 'react';
import { Accept, FileRejection, useDropzone } from 'react-dropzone';
import { Button } from './ui/button';
import { Label } from './ui/label';

interface FileStatus {
    id: string;
    status: 'uploading' | 'processing' | 'success' | 'error';
    fileName: string;
    previewUrl?: string;
    errorMessage?: string;
    progress?: number;
}

interface UploadConfig {
    url: string;
    payload: Record<string, any>; // Data tambahan seperti npwp, id_perusahaan, type
}

interface ResettableDropzoneProps {
    onFileChange: (file: File | null, response?: any) => void;
    label: string;
    isRequired?: boolean;
    existingFile?: { nama_file: string; path: string };
    validation?: { accept?: Accept; maxSize?: number };
    uploadConfig?: UploadConfig;
}

export function ResettableDropzone({
    onFileChange,
    label,
    isRequired = false,
    existingFile,
    validation = { accept: { 'application/pdf': ['.pdf'] }, maxSize: 5 * 1024 * 1024 },
    uploadConfig,
}: ResettableDropzoneProps) {
    const [fileStatus, setFileStatus] = useState<FileStatus | null>(null);
    const [componentKey, setComponentKey] = useState(Date.now());

    React.useEffect(() => {
        if (existingFile && existingFile.path) {
            setFileStatus({
                id: `existing-${existingFile.nama_file}`,
                status: 'success',
                fileName: existingFile.nama_file,
                previewUrl: existingFile.path,
            });
        }
    }, [existingFile]);

    const handleUpload = async (file: File) => {
        if (!uploadConfig) {
            // Fallback ke behavior lama jika tidak ada config upload
            setFileStatus({
                id: String(Date.now()),
                status: 'success',
                fileName: file.name,
                previewUrl: URL.createObjectURL(file),
            });
            onFileChange(file);
            return;
        }

        // Set initial uploading state
        setFileStatus({
            id: String(Date.now()),
            status: 'uploading',
            fileName: file.name,
            progress: 0,
        });

        const formData = new FormData();
        formData.append('file', file);

        // Append dynamic payload (npwp, type, order, id_perusahaan)
        Object.keys(uploadConfig.payload).forEach((key) => {
            formData.append(key, uploadConfig.payload[key]);
        });

        try {
            const res = await axios.post(uploadConfig.url, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (progressEvent) => {
                    const total = progressEvent.total || file.size;
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / total);

                    setFileStatus((prev) => {
                        if (!prev) return null;

                        const isProcessing = percentCompleted === 100;
                        return {
                            ...prev,
                            status: isProcessing ? 'processing' : 'uploading',
                            progress: percentCompleted,
                        };
                    });
                },
            });

            // Handle Response Sukses dari Controller
            setFileStatus({
                id: String(Date.now()),
                status: 'success',
                fileName: res.data.nama_file || file.name,
                previewUrl: res.data.path, // Menggunakan path dari response backend
            });

            // Pass file original dan response backend ke parent
            onFileChange(file, res.data);
        } catch (error: any) {
            console.error(error);
            const msg = error.response?.data?.error || 'Gagal mengupload file';
            setFileStatus({
                id: String(Date.now()),
                status: 'error',
                fileName: file.name,
                errorMessage: msg,
            });
            onFileChange(null);
        }
    };

    const onDrop = React.useCallback(
        (acceptedFiles: File[], fileRejections: FileRejection[]) => {
            if (fileRejections.length > 0) {
                const rejection = fileRejections[0];
                setFileStatus({
                    id: String(Date.now()),
                    status: 'error',
                    fileName: rejection.file.name,
                    errorMessage: rejection.errors[0].message,
                });
                onFileChange(null);
            } else if (acceptedFiles.length > 0) {
                const file = acceptedFiles[0];
                handleUpload(file);
            }
        },
        [onFileChange, uploadConfig],
    );

    const { getRootProps, getInputProps, isDragReject } = useDropzone({
        onDrop,
        maxFiles: 1,
        accept: validation.accept,
        maxSize: validation.maxSize,
        disabled: fileStatus?.status === 'uploading' || fileStatus?.status === 'processing',
    });

    const handleDelete = (e: React.MouseEvent) => {
        e.stopPropagation();
        setFileStatus(null);
        onFileChange(null);
        setComponentKey(Date.now());
    };

    const borderColor = isDragReject ? 'border-red-500' : 'border-gray-300';

    const fixedPreviewUrl =
        fileStatus?.previewUrl && fileStatus.previewUrl.startsWith('/')
            ? fileStatus.previewUrl
            : fileStatus?.previewUrl
              ? `/customer/${fileStatus.previewUrl}`
              : null;

    return (
        <div className="w-full">
            <Label className="mb-1 block">
                {label} {isRequired && <span className="text-red-500">*</span>}
            </Label>
            <div
                key={componentKey}
                {...getRootProps()}
                className={cn(
                    'flex h-25 min-h-25 cursor-pointer items-center justify-center rounded-md border-2 border-black p-4 text-center transition-colors md:h-25 md:min-h-50 dark:border-neutral-800',
                    borderColor,
                )}
            >
                <input {...getInputProps()} />

                {fileStatus ? (
                    <div className="relative flex h-full w-full flex-row items-center justify-center gap-3 rounded-md bg-gray-100 p-2 text-gray-700 md:flex-col md:gap-0">
                        {fileStatus.status !== 'uploading' && fileStatus.status !== 'processing' && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="absolute top-1 right-1 h-6 w-6 rounded-full bg-white p-1 shadow-md"
                                onClick={handleDelete}
                            >
                                <Trash2Icon className="size-4 text-black" />
                            </Button>
                        )}
                        <FileIcon className="mb-2 h-10 w-10" />
                        <p className="hidden max-w-full truncate text-sm font-medium md:block">{fileStatus.fileName}</p>
                        {/* STATUS: UPLOADING */}
                        {(fileStatus.status === 'uploading' || fileStatus.status === 'processing') && (
                            <div className="mt-2 w-full max-w-[90%]">
                                <div className="mb-1 flex items-center justify-center text-xs font-semibold text-gray-600">
                                    <div className="flex items-center gap-2">
                                        {fileStatus.status === 'processing' && <Loader2 className="h-3 w-3 animate-spin items-center text-black" />}
                                        <span className={fileStatus.status === 'processing' ? 'text-black' : ''}>
                                            {fileStatus.status === 'processing' ? '' : 'Uploading...'}
                                        </span>
                                    </div>
                                </div>

                                {/* Progress Bar Track */}
                                <div className="relative w-full">
                                    <Progress
                                        value={fileStatus.progress}
                                        className={cn(
                                            'h-2.5 w-full bg-gray-200', // Style untuk Track (Latar belakang bar)
                                            // Mengubah warna Indicator (bar yang jalan) secara dinamis
                                            // Syntax [&>*] menargetkan child element (Indicator) dari komponen Progress
                                            fileStatus.status === 'processing' ? 'bg-black' : 'bg-black',
                                        )}
                                    />
                                    <div className="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white drop-shadow-[0_1.2px_1.2px_rgba(0,0,0,0.8)]">
                                        {fileStatus.progress}%
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* STATUS: ERROR */}
                        {fileStatus.status === 'error' && <p className="mt-1 text-xs text-red-600">{fileStatus.errorMessage}</p>}

                        {/* STATUS: SUCCESS */}
                        {fileStatus.status === 'success' && fixedPreviewUrl && (
                            <a
                                href={fixedPreviewUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mt-2 text-xs text-blue-600 underline"
                                onClick={(e) => e.stopPropagation()}
                            >
                                Lihat File
                            </a>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2 text-sm text-gray-500 dark:text-white">
                        <CloudUploadIcon className="h-10 w-10" />
                        <p>Klik atau drag file PDF ke sini</p>
                    </div>
                )}
            </div>
        </div>
    );
}
