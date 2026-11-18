/**
 * SkillSwap Campus — Layout Visiteur & Public (GuestLayout)
 */

import React from 'react';
import { Outlet } from 'react-router-dom';
import Navbar from '../components/navbar/Navbar';
import Footer from '../components/footer/Footer';

interface GuestLayoutProps {
    children?: React.ReactNode;
}

export const GuestLayout: React.FC<GuestLayoutProps> = ({ children }) => {
    return (
        <div className="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800 antialiased selection:bg-indigo-100 selection:text-indigo-900">
            <Navbar />
            <main className="flex-1 flex flex-col">
                {children ? children : <Outlet />}
            </main>
            <Footer />
        </div>
    );
};

export default GuestLayout;
