/**
 * @sw-package framework
 */

const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');
const fs = require('node:fs');
const path = require('node:path');

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT;
const root = __dirname;
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));
const { transformShopwareSetupSfc } = requireFromAdmin('./build/vue-setup-transform/index.js');
const remapping = requireFromAdmin('@jridgewell/remapping');
const virtualSuffix = '.shopware-setup.vue';

function resolveMapSourceFileName(outputDirectory, chunkFileName, source) {
    if (path.isAbsolute(source)) {
        return source;
    }

    return path.resolve(outputDirectory, path.dirname(chunkFileName), source);
}

function getOutputDirectory(outputOptions) {
    if (outputOptions.dir) {
        return outputOptions.dir;
    }

    if (outputOptions.file) {
        return path.dirname(outputOptions.file);
    }

    return process.cwd();
}

module.exports = (async () => {
    const { default: vue } = await import(pathToFileURL(requireFromAdmin.resolve('@vitejs/plugin-vue')).href);
    const setupMaps = new Map();
    const setupPlugin = {
        name: 'shopware-setup-probe',
        enforce: 'pre',
        async resolveId(source, importer) {
            if (source.includes('?') || !source.endsWith('.vue')) {
                return null;
            }

            const resolved = await this.resolve(source, importer, { skipSelf: true });

            if (!resolved) {
                return null;
            }

            const filename = resolved.id.split('?')[0];
            const code = fs.readFileSync(filename, 'utf8');
            const result = transformShopwareSetupSfc(code, filename);

            if (!result) {
                return null;
            }

            setupMaps.set(`${filename}${virtualSuffix}`, result.map);

            return `${filename}${virtualSuffix}`;
        },
        load(id) {
            if (!id.endsWith(virtualSuffix)) {
                return null;
            }

            const filename = id.slice(0, -virtualSuffix.length);
            const code = fs.readFileSync(filename, 'utf8');
            const result = transformShopwareSetupSfc(code, filename);

            return result ? { code: result.code, map: result.map } : null;
        },
        generateBundle(outputOptions, bundle) {
            const outputDirectory = getOutputDirectory(outputOptions);

            Object.values(bundle).forEach((item) => {
                if (item.type !== 'chunk' || !item.map) {
                    return;
                }

                if (!item.map.sources.some((source) => source.endsWith(virtualSuffix))) {
                    return;
                }

                item.map = remapping(
                    item.map,
                    (source) => {
                        if (!source.endsWith(virtualSuffix)) {
                            return null;
                        }

                        const virtualFileName = resolveMapSourceFileName(outputDirectory, item.fileName, source);

                        return setupMaps.get(virtualFileName) ?? null;
                    },
                    false,
                );
            });
        },
    };

    return {
        root,
        logLevel: 'silent',
        plugins: [setupPlugin, vue()],
        build: {
            write: false,
            sourcemap: true,
            minify: false,
            rollupOptions: {
                input: path.join(root, 'src/Entry.js'),
                external: ['vue'],
            },
        },
    };
})();
