/**
 * @package framework
 */
import fs from 'fs';
import path from 'path';
import * as net from 'net';

function isPortFree(port: number): Promise<boolean | Error> {
    return new Promise((resolve) => {
        const server = net.createServer();

        server.listen({ port }, () => {
            server.close(() => {
                resolve(true);
            });
        });

        server.on('error', () => {
            resolve(false);
        });
    });
}

/**
 * @private
 */
export function findFilesRecursively(dir: string, pattern: RegExp): string[] {
    let results = [] as string[];

    // Read directory contents
    const items = fs.readdirSync(dir, { withFileTypes: true });

    items.forEach((item) => {
        const fullPath = path.join(dir, item.name);

        if (item.isDirectory()) {
            // Skip node_modules
            if (item.name === 'node_modules') {
                return;
            }

            // Recurse into subdirectories
            results = results.concat(findFilesRecursively(fullPath, pattern));
        } else if (item.isFile() && pattern.test(item.name)) {
            // Add matching files
            results.push(fullPath);
        }
    });

    return results;
}

/**
 * @private
 */
export function copyDir(src: string, dest: string): void {
    // Create destination directory
    if (!fs.existsSync(dest)) {
        fs.mkdirSync(dest, { recursive: true });
    }

    // Read source directory
    const entries = fs.readdirSync(src, { withFileTypes: true });

    entries.forEach((entry) => {
        const srcPath = path.join(src, entry.name);
        const destPath = path.join(dest, entry.name);

        if (entry.isDirectory()) {
            // Recursively copy directory
            copyDir(srcPath, destPath);
        } else {
            // Copy file
            fs.copyFileSync(srcPath, destPath);
        }
    });
}

/**
 * @private
 */
export type PluginDefinition = {
    name: string;
    technicalName: string;
    technicalFolderName: string;
    basePath: string;
    path: string;
    filePath: string;
    hasHtmlFile: boolean;
};

/**
 * @private
 *
 * Create an array with information about all injected plugins.
 *
 * The given structure looks like this:
 * [
 *   {
 *      name: 'SwagExtensionStore',
 *      technicalName: 'swag-extension-store',
 *      basePath: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src',
 *      path: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/src',
 *      filePath: '/Users/max.muster/.../custom/plugins/SwagExtensionStore/src/Resources/app/administration/src/main.js',
 *   },
 *    ...
 * ]
 */
export function loadPlugins(): PluginDefinition[] {
    const pluginFile = path.resolve(process.env.PROJECT_ROOT as string, 'var/plugins.json');

    if (!fs.existsSync(pluginFile)) {
        throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8')) as {
        [BundleName: string]: {
            basePath: string;
            views: string[];
            technicalName: string;
            isTheme: boolean;
            administration?: {
                path: string;
                entryFilePath: string | null;
            };
        };
    };

    return Object.entries(pluginDefinition)
        .filter(
            ([
                name,
                definition,
            ]) =>
                !!definition.administration &&
                !!definition.administration.entryFilePath &&
                !process.env.hasOwnProperty(`SKIP_${definition.technicalName.toUpperCase().replace(/-/g, '_')}`),
        )
        .map(
            ([
                name,
                definition,
            ]) => {
                const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
                const htmlFilePath = path.resolve(
                    process.env.PROJECT_ROOT as string,
                    definition.basePath,
                    // @ts-expect-error - We know it is defined at this point because of the filter above
                    definition.administration.path,
                    '..',
                    'index.html',
                );
                const hasHtmlFile = fs.existsSync(htmlFilePath);

                return {
                    name,
                    technicalName: technicalName,
                    technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
                    basePath: path.resolve(process.env.PROJECT_ROOT as string, definition.basePath),
                    path: path.resolve(
                        process.env.PROJECT_ROOT as string,
                        definition.basePath,
                        // @ts-expect-error - We know it is defined at this point because of the filter above
                        definition.administration.path,
                    ),
                    filePath: path.resolve(
                        process.env.PROJECT_ROOT as string,
                        definition.basePath,
                        // @ts-expect-error - We know it is defined at this point because of the filter above
                        definition.administration.entryFilePath,
                    ),
                    hasHtmlFile,
                };
            },
        );
}

/**
 * @private
 */
export async function findAvailablePorts(startPort = 5173, requiredPorts = 1): Promise<number[]> {
    const ports = [];
    let currentPort = startPort;
    const maxPort = 6333;

    while (ports.length < requiredPorts) {
        if (currentPort > maxPort) {
            throw new Error(`No free ports found between ${startPort} and ${maxPort}`);
        }

        // eslint-disable-next-line no-await-in-loop
        const isFree = await isPortFree(currentPort);
        if (isFree) {
            ports.push(currentPort);
        }
        currentPort += 1;
    }

    return ports;
}
