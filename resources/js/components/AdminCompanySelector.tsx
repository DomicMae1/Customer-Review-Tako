import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronDown, Globe } from 'lucide-react';
import { useState } from 'react';

export function AdminCompanySelector() {
    const { all_companies, admin_active_company_id, auth } = usePage<SharedData>().props;

    const isAdmin = auth?.user?.roles?.some((r: { name: string }) => r.name === 'admin');

    // Hanya render untuk admin dengan perusahaan tersedia
    if (!isAdmin || !all_companies || all_companies.length === 0) {
        return null;
    }

    const activeCompany = all_companies.find((c) => c.id === admin_active_company_id) ?? null;
    const [isLoading, setIsLoading] = useState(false);

    const handleSelect = (companyId: number | null) => {
        if (isLoading) return;
        setIsLoading(true);
        router.post(
            '/admin/set-company',
            { company_id: companyId },
            {
                preserveScroll: true,
                onFinish: () => setIsLoading(false),
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className={`h-8 max-w-[220px] gap-1.5 text-xs font-medium ${
                        activeCompany
                            ? 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                            : 'text-muted-foreground'
                    }`}
                    disabled={isLoading}
                    id="admin-company-selector"
                >
                    {activeCompany ? (
                        <>
                            <Building2 className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">{activeCompany.nama_perusahaan}</span>
                        </>
                    ) : (
                        <>
                            <Globe className="h-3.5 w-3.5 shrink-0" />
                            <span>Semua Perusahaan</span>
                        </>
                    )}
                    <ChevronDown className="ml-auto h-3 w-3 shrink-0 opacity-50" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                    Lihat Data Sebagai
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {/* Opsi: Semua Perusahaan */}
                <DropdownMenuItem
                    onClick={() => handleSelect(null)}
                    className="flex items-center gap-2 text-sm"
                    id="admin-company-all"
                >
                    <Globe className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span className="flex-1">Semua Perusahaan</span>
                    {!activeCompany && <Check className="h-4 w-4 text-primary" />}
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                {/* Daftar perusahaan */}
                <div className="max-h-64 overflow-y-auto">
                    {all_companies.map((company) => (
                        <DropdownMenuItem
                            key={company.id}
                            onClick={() => handleSelect(company.id)}
                            className="flex items-center gap-2 text-sm"
                            id={`admin-company-${company.id}`}
                        >
                            <Building2 className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="flex-1 truncate">{company.nama_perusahaan}</span>
                            {activeCompany?.id === company.id && (
                                <Check className="h-4 w-4 shrink-0 text-primary" />
                            )}
                        </DropdownMenuItem>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}