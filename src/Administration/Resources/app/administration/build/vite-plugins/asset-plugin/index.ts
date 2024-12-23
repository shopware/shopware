import path from 'path';
import fs from 'fs';
import { contentType } from 'mime-types';
import type { Plugin } from 'vite';
import { copyDir } from '../utils';

/**
 * @package framework
 * @private
 *
 * This plugin simply copies the static folder into public for production and
 * serves the assets for the dev server.
 */
export default function viteAssetPlugin(isProd: boolean, adminDir: string): Plugin {
    // Copy over all static assets for production
    if (isProd) {
        return {
            name: 'shopware-vite-plugin-copy-static-assets',
            // Hook into the build process after it's done
            closeBundle() {
                const staticDir = path.resolve(adminDir, 'static');
                const outDir = path.resolve(adminDir, '../../public/administration/static');

                // Ensure the static directory exists
                if (fs.existsSync(staticDir)) {
                    // Copy the entire static directory to outDir/static
                    copyDir(staticDir, outDir);
                }
            },
        };
    }

    return {
        name: 'shopware-vite-plugin-serve-multiple-static',

        configureServer(server) {
            const staticMappings = [
                {
                    directory: path.resolve(adminDir, 'static'),
                    publicPath: '/static',
                },
                {
                    directory: path.resolve(adminDir, 'static'),
                    publicPath: '/administration/static',
                },
                {
                    directory: path.resolve(adminDir, 'static'),
                    publicPath: '/bundles/administration/static',
                },
            ];

            server.middlewares.use((req, res, next) => {
                const originalUrl = req.originalUrl;

                if (!originalUrl) {
                    return next();
                }

                // Add a custom route for sw-plugin-dev.json
                if (originalUrl.endsWith('sw-plugin-dev.json')) {
                    const pluginDevContent = fs.readFileSync(
                        path.resolve(adminDir, '../../public/administration/sw-plugin-dev.json'),
                        'utf8',
                    );

                    res.writeHead(200, {
                        'Content-Type': 'application/json',
                        'Content-Length': Buffer.byteLength(pluginDevContent),
                    });
                    res.end(pluginDevContent);
                    return;
                }

                // Check if the URL matches any of the static mappings and use the first match
                const match = staticMappings.find((mapping) => {
                    if (originalUrl.startsWith(mapping.publicPath)) {
                        return true;
                    }
                });

                if (!match) {
                    return next();
                }

                // When URL starts with the public path, we need to serve the file from the directory
                const filePath = path.join(match.directory, originalUrl.replace(match.publicPath, ''));
                const stats = fs.statSync(filePath, { throwIfNoEntry: false });

                // Check if the file exists
                if (!stats || !stats.isFile()) {
                    res.writeHead(404);
                    res.end('Not found');
                    console.error(`File not found: ${filePath}`);
                    return;
                }

                // Set the content type based on the file extension
                const type = contentType(path.basename(filePath)) as string;

                // Write correct headers and pipe the file to the response
                res.writeHead(200, {
                    'Content-Length': stats.size,
                    'Content-Type': type,
                });

                const stream = fs.createReadStream(filePath);
                stream.pipe(res);
            });
        },
    };
}
