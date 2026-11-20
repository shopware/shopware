/**
 * This file is the entry point for the Vite build process for plugins.
 * Depending on the environment variable VITE_MODE, it will either start a dev server
 * for each plugin or build the plugins for production.
 *
 * The environment variable VITE_MODE is automatically set by the npm commands in the package.json.
 * You can just run `composer build:js:admin` or `composer watch:admin` respectively.
 *
 * @sw-package framework
 */

import path from 'path';
import colors from 'picocolors';
import {
    loadExtensions,
    getExtensionBasePath,
    getViteServerPorts,
    runForEachExtension,
    writePluginDevJson,
} from './vite-plugins/utils';
import { getExtensionBuildConfig, getExtensionDevConfig } from './vite-plugins/extension-config';
import { isBundledDevActive, loadViteToolchain } from './vite-plugins/vite-toolchain';

const VITE_MODE = process.env.VITE_MODE || 'development';
const isDev = VITE_MODE === 'development';

const extensionEntries = loadExtensions();

// Main function to handle both dev and build modes
const main = async () => {
    const toolchain = await loadViteToolchain();

    if (isDev) {
        const extensionPorts = getViteServerPorts();
        const useBundledDev = isBundledDevActive(isDev);

        if (useBundledDev) {
            console.log(colors.yellow('# Experimental bundled dev mode activated for plugins'));
        }

        writePluginDevJson(path.dirname(__dirname), extensionEntries, useBundledDev);

        await runForEachExtension(extensionEntries, 'start', async (extension) => {
            const server = await toolchain.vite.createServer({
                ...getExtensionDevConfig(toolchain, extension, useBundledDev),
                server: {
                    host: '127.0.0.1',
                    port: extensionPorts[getExtensionBasePath(extension)],
                    strictPort: true,
                    cors: true,
                },
            });

            console.log(colors.green(`# ${extension.isApp ? 'App' : 'Plugin'} "${extension.name}": Injected successfully`));

            await server.listen();
            server.printUrls();
        });

        return;
    }

    const hasFailedBuilds = await runForEachExtension(extensionEntries, 'build', async (extension) => {
        console.log(colors.green(`# Building ${extension.isApp ? 'app' : 'plugin'} "${extension.name}"`));
        await toolchain.vite.build(getExtensionBuildConfig(toolchain, extension));
    });

    if (hasFailedBuilds) {
        console.error(colors.red('One or more builds failed. Check the logs above for details.'));
        process.exit(1);
    }
};

main().catch((error) => {
    console.error(error);

    // In dev mode a non-zero exit would also stop the watcher of the Administration
    if (!isDev) {
        process.exit(1);
    }
});
