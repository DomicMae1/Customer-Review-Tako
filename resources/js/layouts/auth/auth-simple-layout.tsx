import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    company_name: string;
    company_logo?: string | null;
    app_name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, company_name, company_logo, app_name, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            {company_logo ? (
                                // Jika ada logo perusahaan, tampilkan gambar
                                <div className="mb-1 flex h-30 w-30 items-center justify-center rounded-2xl border bg-white p-2 shadow-sm md:h-40 md:w-40">
                                    <img src={company_logo} alt={company_name || 'Company Logo'} className="h-full w-full object-contain" />
                                </div>
                            ) : (
                                // Fallback: Logo Default Aplikasi
                                <div className="mb-1 flex h-30 w-30 items-center justify-center rounded-2xl bg-white md:h-40 md:w-40">
                                    <AppLogoIcon className="h-full w-full object-contain" />
                                </div>
                            )}
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-4xl font-bold">{company_name}</h1>
                            <h1 className="text-2xl font-medium">{app_name}</h1>
                            <h3 className="text-muted-foreground text-lg">{title}</h3>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
