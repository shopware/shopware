import path from 'path';
import fs from 'fs';
import { contentType } from 'mime-types';
import type { Plugin } from 'vite';
import { copyDir, ExtensionDefinition } from '../utils';

/**
 * @sw-package framework
 * @private
 *
 * This plugin serves static assets for the administration and administration extensions.
 *
 * In production, it copies assets from Resources/app/administration/static into Resources/public/administration/static,
 * which are then copied over into their respective <root>/public/bundles/<bundle>/administration/static directory in a later build step.
 *
 * In development, it directly serves the static assets from Resources/app/administration/static over the vite server.
 */
export default function viteAssetPlugin(isProd: boolean, adminDir: string, extensions: ExtensionDefinition[] = []): Plugin {
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
            /**
             * The mapping is used to serve static assets from the platform/extension source directory (e.g. Resources/app/administration/static)
             * during development mode instead of their public directory, which is only updated during build.
             */
            const staticMappings = [
                {
                    directory: path.resolve(adminDir, 'static'),
                    publicPath: '/bundles/administration/administration/static',
                },
                ...extensions.map((extension) => ({
                    directory: path.resolve(extension.basePath, 'Resources/app/administration/static'),
                    publicPath: `/bundles/${extension.technicalFolderName}/administration/static`,
                })),
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
