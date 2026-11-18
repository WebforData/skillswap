/**
 * SkillSwap Campus — Hook useAuth
 */

import { useContext } from 'react';
import { AuthContext, AuthContextType } from '../contexts/AuthContext';

export const useAuth = (): AuthContextType => {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error("useAuth doit être utilisé au sein d'un composant enveloppé par AuthProvider.");
    }

    return context;
};

export default useAuth;
