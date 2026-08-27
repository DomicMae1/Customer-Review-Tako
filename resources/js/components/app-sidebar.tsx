import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type MainNavItem, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, Landmark, Shield, SquareUserRound, Users, Truck } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: MainNavItem[] = [
    {
        title: 'Customers',
        url: '/customer',
        icon: SquareUserRound,
        permissions: ['customer.view'],
    },
    {
        title: 'Bank Customer',
        url: '/bank-customer',
        icon: Landmark,
        permissions: ['customer.bank.view'],
    },
    {
        title: 'Suppliers',
        url: '/supplier',
        icon: Truck,
        permissions: ['supplier.view'],
    },
    {
        title: 'Bank Supplier',
        url: '/bank-supplier',
        icon: Landmark,
        permissions: ['supplier.bank.view'],
    },
    {
        title: 'Manage Users',
        url: '/users',
        icon: Users,
        permissions: ['user.view'],
    },
    {
        title: 'Manage Role',
        url: '/role-manager',
        icon: Shield,
        permissions: ['role.view'],
    },
    {
        title: 'Manage Company',
        url: '/perusahaan',
        icon: Building2,
        permissions: ['perusahaan.view'],
    },
];

// const footerNavItems: NavItem[] = [
//     {
//         title: 'Repository',
//         url: 'https://github.com/laravel/react-starter-kit',
//         icon: Folder,
//     },
//     {
//         title: 'Documentation',
//         url: 'https://laravel.com/docs/starter-kits',
//         icon: BookOpen,
//     },
// ];

export function AppSidebar() {
    const { setOpen } = useSidebar();
    const { auth } = usePage<PageProps>().props;

    const isAdmin = auth?.user?.roles?.some((role: { name: string }) => role.name === 'admin');

    const userPermissions = auth?.user?.permissions || [];

    const hasPermission = (requiredPermissions: string[] = []) => {
        if (isAdmin) return true;
        return requiredPermissions.length === 0 || requiredPermissions.some((perm) => userPermissions.includes(perm));
    };

    const filteredNavItems = mainNavItems
        .map((item) => {
            if ('subItems' in item) {
                const filteredSubItems =
                    item.subItems?.filter((subItem) => hasPermission(subItem.permissions)) || [];

                if (filteredSubItems.length > 0 || hasPermission(item.permissions)) {
                    return { ...item, subItems: filteredSubItems };
                }

                return null;
            }

            return hasPermission(item.permissions) ? item : null;
        })
        .filter((item): item is MainNavItem => item !== null);

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                        >
                            <Link href="/customer" prefetch>
                                <span
                                    className="flex min-w-0 flex-1 items-center"
                                    onClick={() => setOpen(true)}
                                >
                                    <AppLogo />
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={filteredNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
