/**
 * @sw-package framework
 */
import type { Plugin, UserConfig } from 'vite';
import path from 'path';
import svgLoader from 'vite-svg-loader';
import debug from 'debug';
import TwigPlugin from '../twigjs-plugin';
import AssetPlugin from '../asset-plugin';
import AssetPathPlugin from '../asset-path-plugin';
import ExternalsPlugin from '../externals-plugin';
import AssetCssPostprocessPlugin from '../asset-css-postprocess-plugin';
import OverrideComponentRegisterPlugin from '../override-component-register';
import ShopwareSetupPlugin from '../shopware-setup';
import ShopwareSetupHmrPlugin from '../shopware-setup-hmr';
import VirtualShopwareModulesPlugin from '../virtual-shopware-modules';
import ExtglobCompatPlugin from '../extglob-compat';
import injectHtml from '../inject-html';
import { getExtensionBasePath } from '../utils';
import type { ExtensionDefinition } from '../utils';
import type { ViteToolchain } from '../vite-toolchain';

const administrationRoot = path.resolve(__dirname, '../../..');
const adminSrcPath = process.env.ADMIN_ROOT
    ? path.join(process.env.ADMIN_ROOT, 'Resources', 'app', 'administration', 'src')
    : path.join(administrationRoot, 'src');

function getExtensionConfig(
    toolchain: ViteToolchain,
    extension: ExtensionDefinition,
    isProd: boolean,
    useBundledDev = false,
): UserConfig {
    const extensionInfoDebug = debug(`vite:${extension.isPlugin ? 'plugin' : 'app'}:${extension.technicalName}`);
    const configInfoDebug = debug('vite:config');
    // On Vite 6 an extension runs on its own dev server behind `/_internal_ext/`, and the Vue HMR record of an
    // override there does not hold the definition the Administration mounted, so a script edit leaves the page stale.
    const scriptHmr = toolchain.isVite8 && !isProd && !useBundledDev;
    const useSourceMap =
        (!isProd && process.env.SHOPWARE_ADMIN_SKIP_SOURCEMAP_GENERATION !== '1') ||
        (isProd && process.env.GENERATE_SOURCEMAPS === 'true');

    const logger = toolchain.vite.createLogger();

    logger.info = (msg) => {
        if (msg.includes('vite:config')) {
            configInfoDebug(msg);
            return;
        }

        extensionInfoDebug(msg);
    };

    return {
        root: extension.path,

        logLevel: isProd ? 'warn' : 'info',

        customLogger: logger,

        cacheDir: path.resolve(extension.path, '..', '.tmp/vite'),

        plugins: [
            TwigPlugin(),
            AssetPlugin(isProd, path.resolve(extension.basePath, 'Resources/app/administration')),
            AssetPathPlugin(extension.technicalFolderName),
            AssetCssPostprocessPlugin(`/bundles/${extension.technicalFolderName}/administration/assets/`),
            svgLoader(),
            OverrideComponentRegisterPlugin({
                root: extension.path,
                pluginEntryFile: extension.filePath,
            }),
            ShopwareSetupPlugin({
                administrationRoot,
                scriptHmr,
            }),
            VirtualShopwareModulesPlugin({
                administrationRoot,
                consumer: 'extension',
            }),
            toolchain.vue({
                template: {
                    compilerOptions: {
                        compatConfig: {
                            MODE: 2,
                        },
                    },
                },
                // Ids are the HMR keys, the same file path in two extensions must not share one.
                ...(scriptHmr
                    ? {
                          features: {
                              componentIdGenerator: (filePath, _source, _isProduction, getHash) =>
                                  `${extension.technicalName}-${getHash(filePath)}`,
                          },
                      }
                    : {}),
            }),
            ...(scriptHmr ? [ShopwareSetupHmrPlugin()] : []),
            ...getCompatPlugins(toolchain),
            ExternalsPlugin(),

            // Prod plugins
            ...(isProd ? [toolchain.symfonyPlugin()] : []),
        ],

        resolve: {
            alias: [
                {
                    find: /^src\//,
                    replacement: '/src/',
                },
                {
                    find: /^~scss\/(.*)/,
                    replacement: `${adminSrcPath}/app/assets/scss/$1.scss`,
                },
                {
                    find: /^~(.*)$/,
                    replacement: '$1',
                },
            ],
            preserveSymlinks: true,
        },

        ...(!isProd
            ? {}
            : {
                  base: `/bundles/${extension.technicalFolderName}/administration/`,
                  optimizeDeps: {
                      include: [
                          'vue-router',
                          'vuex',
                          'vue-i18n',
                          'flatpickr',
                          'flatpickr/**/*',
                          'date-fns-tz',
                      ],
                      holdUntilCrawlEnd: true,
                      ...(toolchain.isVite8
                          ? {
                                rolldownOptions: {
                                    transform: {
                                        define: {
                                            global: 'globalThis',
                                        },
                                    },
                                },
                            }
                          : {
                                esbuildOptions: {
                                    define: {
                                        global: 'globalThis',
                                    },
                                },
                            }),
                  },
              }),

        css: {
            lightningcss: {
                errorRecovery: true,
            },
        },

        // Typed with Vite 6, which does not know the bundled dev mode
        ...(useBundledDev ? { experimental: { bundledDev: true } as UserConfig['experimental'] } : {}),

        build: {
            outDir: path.resolve(extension.basePath, 'Resources/public/administration'),
            emptyOutDir: true,
            manifest: true,
            sourcemap: useSourceMap,
            rollupOptions: {
                input: {
                    [extension.technicalName]: extension.filePath,
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                },
                // Rolldown requests lazy chunks from the absolute `/@vite/lazy` URL, which ignores `base` and hits the main admin server
                ...(useBundledDev ? { experimental: { devMode: { lazy: false } } } : {}),
            },
        },
    };
}

/**
 * @private
 *
 * The config of the dev server of an extension, without the server options.
 */
export function getExtensionDevConfig(
    toolchain: ViteToolchain,
    extension: ExtensionDefinition,
    useBundledDev: boolean,
): UserConfig {
    const config: UserConfig = extension.isApp
        ? {
              root: extension.path,
              plugins: getCompatPlugins(toolchain),
          }
        : getExtensionConfig(toolchain, extension, false, useBundledDev);

    return { ...config, base: getExtensionBasePath(extension) };
}

/**
 * @private
 */
export function getExtensionBuildConfig(toolchain: ViteToolchain, extension: ExtensionDefinition): UserConfig {
    if (!extension.isApp) {
        return getExtensionConfig(toolchain, extension, true);
    }

    return {
        root: extension.path,
        base: '',
        build: {
            outDir: path.resolve(extension.basePath, 'Resources/public/meteor-app'),
        },
        plugins: [
            injectHtml([
                {
                    tag: 'base',
                    attrs: {
                        href: '__$ASSET_BASE_PATH$__',
                    },
                    injectTo: 'head-prepend',
                },
            ]),
            ...getCompatPlugins(toolchain),
        ],
    };
}

function getCompatPlugins(toolchain: ViteToolchain): Plugin[] {
    return toolchain.isVite8 ? [ExtglobCompatPlugin({ warnOnRewrite: true })] : [];
}
