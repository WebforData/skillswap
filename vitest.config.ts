import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'src': path.resolve(__dirname, 'resources/js'),
        },
        extensions: ['.tsx', '.ts', '.jsx', '.js', '.json'],
    },
    test: {
        globals: true,
        include: ['resources/js/**/*.test.{ts,tsx}'],
        environment: 'jsdom',
        setupFiles: ['./resources/js/test/setup.ts'],
    },
});
