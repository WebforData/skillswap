/**
 * SkillSwap Campus — Layout Étudiant Connecté (AppLayout)
 */

import React, { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from '../components/navbar/Navbar';
import Sidebar from '../components/navbar/Sidebar';
import Footer from '../components/footer/Footer';

interface AppLayoutProps {
    children?: React.ReactNode;
}

export const AppLayout: React.FC<AppLayoutProps> = ({ children }) => {
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800 antialiased selection:bg-indigo-100 selection:text-indigo-900">
            <Navbar
                showSidebarToggle={true}
                onToggleMobileSidebar={() => setMobileSidebarOpen(true)}
            />

            <div className="flex-1 flex w-full max-w-7xl mx-auto">
                <Sidebar
                    type="student"
                    isOpen={mobileSidebarOpen}
                    onClose={() => setMobileSidebarOpen(false)}
                />

                <main className="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 overflow-y-auto">
                    {children ? children : <Outlet />}
                </main>
            </div>

            <Footer />
        </div>
    );
};

export default AppLayout;
