import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        // Generate manifest.json in the build directory
        manifest: true,
        // Ensure all assets are included
        assetsInlineLimit: 0,
        outDir: 'public/build',
    },
    // Make Vite respect the ASSET_URL environment variable
    base: process.env.ASSET_URL || '',
});
