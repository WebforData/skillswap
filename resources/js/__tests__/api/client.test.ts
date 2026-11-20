import axios, { AxiosError } from 'axios';
import { afterEach, describe, expect, it } from 'vitest';
import apiClient, { getStoredToken, setStoredToken, removeStoredToken } from '../../api/client';

const originalAdapter = apiClient.defaults.adapter;
afterEach(() => {
    apiClient.defaults.adapter = originalAdapter;
    removeStoredToken();
});
function rejectStatus(status: number, data: Record<string, unknown>) {
    apiClient.defaults.adapter = async (config) => {
        throw new AxiosError('HTTP error', undefined, config, undefined, {
            status,
            data,
            statusText: 'Error',
            headers: {},
            config,
        });
    };
}
describe('API errors consumed by forms', () => {
    it('retains the HTTP status and field errors for form validation', async () => {
        rejectStatus(422, {
            message: 'Profil invalide.',
            errors: { email: ['Adresse déjà utilisée.'] },
        });
        try {
            await apiClient.put('/auth/profile', {});
            throw new Error('Request should fail');
        } catch (error) {
            expect(axios.isAxiosError(error)).toBe(true);
            if (!axios.isAxiosError(error)) throw error;
            expect(error.response?.status).toBe(422);
            expect(error.response?.data.errors.email).toEqual(['Adresse déjà utilisée.']);
        }
    });
    it('retains invalid-login responses while revoking a rejected token', async () => {
        setStoredToken('test-token');
        rejectStatus(401, { message: 'Identifiants incorrects.' });
        await expect(apiClient.post('/auth/login', {})).rejects.toMatchObject({
            response: { status: 401 },
        });
        expect(getStoredToken()).toBeNull();
    });
    it('preserves a session on an ordinary forbidden action', async () => {
        setStoredToken('test-token');
        rejectStatus(403, { message: 'Accès interdit.' });
        await expect(apiClient.get('/admin/users')).rejects.toMatchObject({
            response: { status: 403 },
        });
        expect(getStoredToken()).toBe('test-token');
    });
    it('hides internal server details without losing the HTTP error type', async () => {
        rejectStatus(500, {
            message: 'Internal path and trace',
            trace: 'sensitive server details',
        });
        try {
            await apiClient.get('/offres');
            throw new Error('Request should fail');
        } catch (error) {
            expect(axios.isAxiosError(error)).toBe(true);
            if (!axios.isAxiosError(error)) throw error;
            expect(error.response?.status).toBe(500);
            expect(error.response?.data).not.toHaveProperty('trace');
            expect(error.response?.data.message).toContain('Une erreur interne');
        }
    });
});
