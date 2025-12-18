import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuShortcut,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { ArrowLeft, ArrowRight, Printer, RefreshCw } from 'lucide-react';
import React, { useEffect } from 'react';

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

const AppLayout = ({ children, breadcrumbs, ...props }: AppLayoutProps) => {
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Blokir F12
            if (e.key === 'F12') {
                e.preventDefault();
            }

            // Blokir Inspect Element (Ctrl+Shift+I / J / C)
            if (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) {
                e.preventDefault();
            }

            // Blokir View Source (Ctrl+U)
            if (e.ctrlKey && e.key.toLowerCase() === 'u') {
                e.preventDefault();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        const antiDebug = setInterval(() => {
            // Function anonim yang memanggil debugger
            (function() {
                // Statement 'debugger' akan menghentikan eksekusi browser 
                // HANYA JIKA Developer Tools sedang terbuka.
                // Jika user biasa (DevTools tutup), ini tidak berefek apa-apa.
                debugger; 
            })();
        }, 1000); // Cek setiap 1 detik

        // ============================================================

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            clearInterval(antiDebug); // Bersihkan interval saat unmount
        };
    }, []);

    // 2. Return JSX
    return (
        <ContextMenu>
            <ContextMenuTrigger className="min-h-screen w-full">
                <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
                    {children}
                </AppLayoutTemplate>
            </ContextMenuTrigger>

            {/* ISI MENU KLIK KANAN CUSTOM */}
            <ContextMenuContent className="w-64">
                <ContextMenuItem inset onClick={() => window.history.back()}>
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back
                    <ContextMenuShortcut>Alt+←</ContextMenuShortcut>
                </ContextMenuItem>

                <ContextMenuItem inset onClick={() => window.history.forward()}>
                    <ArrowRight className="mr-2 h-4 w-4" />
                    Forward
                    <ContextMenuShortcut>Alt+→</ContextMenuShortcut>
                </ContextMenuItem>

                <ContextMenuSeparator />

                <ContextMenuItem inset onClick={() => window.location.reload()}>
                    <RefreshCw className="mr-2 h-4 w-4" />
                    Reload
                    <ContextMenuShortcut>Ctrl+R</ContextMenuShortcut>
                </ContextMenuItem>

                <ContextMenuSeparator />

                <ContextMenuItem inset onClick={() => window.print()}>
                    <Printer className="mr-2 h-4 w-4" />
                    Print
                    <ContextMenuShortcut>Ctrl+P</ContextMenuShortcut>
                </ContextMenuItem>
            </ContextMenuContent>
        </ContextMenu>
    );
};

export default AppLayout;
