/**
 * Vite Configuration
 * ==================
 *
 * Configures the Vite build tool for the Laravel + Vue.js frontend:
 *
 * - Tailwind CSS v4 via the @tailwindcss/vite plugin
 * - Laravel Vite plugin for asset bundling and HMR integration
 * - Wayfinder plugin for type-safe route/action generation
 * - Vue 3 SFC compilation with asset URL transformation
 *
 * The dev server is configured to listen on all interfaces (0.0.0.0)
 * to work correctly inside Docker containers, with HMR proxied
 * through localhost.
 */

import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        // Tailwind CSS v4 — processes utility classes at build time
        tailwindcss(),

        // Laravel Vite plugin — handles asset entry points and HMR
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: [
                'resources/views/**',
                'routes/**',
                'app/Http/Controllers/**',
                'resources/js/**',
                'app/Providers/**',
            ],
        }),

        // Wayfinder — generates TypeScript-safe route helpers from Laravel routes
        wayfinder({
            formVariants: true,
        }),

        // Vue 3 — SFC compilation with Inertia-compatible asset URL handling
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],

    // Dev server configuration (optimized for Docker)
    server: {
        host: '0.0.0.0',        // Listen on all interfaces (required inside Docker)
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',   // HMR WebSocket connects back through localhost
        },
    },
});