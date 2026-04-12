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
            <div className="w-full">
                <div className="flex flex-col gap-8">
                    {/* HEADER (LEBAR BESAR) */}
                    <div className="mx-auto flex max-w-2xl flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            {company_logo ? (
                                <div className="mb-1 flex h-30 w-30 items-center justify-center rounded-2xl border bg-white p-2 shadow-sm md:h-40 md:w-40">
                                    <img src={company_logo} alt={company_name || 'Company Logo'} className="h-full w-full object-contain" />
                                </div>
                            ) : (
                                <div className="mb-1 flex h-30 w-30 items-center justify-center rounded-2xl bg-white md:h-40 md:w-40">
                                    <AppLogoIcon className="h-full w-full object-contain" />
                                </div>
                            )}
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl leading-tight font-bold md:text-4xl">{company_name}</h1>
                            <h1 className="text-2xl font-medium md:pt-6">{app_name}</h1>
                            <h3 className="text-muted-foreground text-lg">{title}</h3>
                            <p className="text-muted-foreground text-sm">{description}</p>
                        </div>
                    </div>

                    {/* FORM (TETAP KECIL) */}
                    <div className="mx-auto w-full max-w-sm">{children}</div>
                </div>
            </div>
        </div>
    );
}
