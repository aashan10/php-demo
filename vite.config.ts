import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite'


export default defineConfig({
    plugins: [
        tailwindcss()
    ],
    build: {
        outDir: 'public/build',
        manifest: true,
        rollupOptions: {
            input: {
                app: 'resources/js/app.ts',
                css: 'resources/css/app.css',
            },
        },
    },
    server: {
        https: false,
        port: 5173,
        hmr: {
            host: 'localhost',
        }
    }
});
