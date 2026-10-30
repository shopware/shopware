/**
 * @sw-package framework
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import Module from 'module';
import os from 'os';
import path from 'path';
import { VITE_8_TOOLCHAIN_PATH } from './index';

const administrationRoot = path.resolve(__dirname, '../../..');
const viteConfigPath = path.join(administrationRoot, 'vite.config.mts');
const viteServerMappingPath = path.join(administrationRoot, 'build/vite-plugins/utils/vite-server-mapping.json');

const vite6Entry = path.join(administrationRoot, 'node_modules/vite/dist/node/index.js');
const vite8Entry = path.join(VITE_8_TOOLCHAIN_PATH, 'node_modules/vite/dist/node/index.js');

const vite6PathPattern = '/administration/node_modules/(vite|@vitejs/[^/]+|vite-plugin-[^/]+|rollup|esbuild)/';
const vite8PathPattern = '/administration/build/vite-8/';

type LoadedPaths = { offending: string[] };

// `module.registerHooks` exists since Node.js 22.15, Docker and CI run newer versions.
const describeWithModuleHooks = 'registerHooks' in Module ? describe : describe.skip;

/**
 * Vite 8 is published as ESM only, which Jest cannot load, so every case runs in a separate Node.js process.
 */
describe('build/vite-plugins/vite-toolchain', () => {
    describeWithModuleHooks('isolation of Vite 6 and Vite 8 (needs Node.js 22.15 or newer)', () => {
        let tempDir: string;
        let createdViteServerMapping = false;

        function createProjectRoot(vite8Active: boolean): string {
            const projectRoot = fs.mkdtempSync(path.join(tempDir, 'project-'));

            fs.mkdirSync(path.join(projectRoot, 'var'));
            fs.writeFileSync(
                path.join(projectRoot, 'var/config_js_features.json'),
                JSON.stringify({ ADMIN_VITE_8: vite8Active }),
            );
            fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), '{}');

            return projectRoot;
        }

        function runScript<T>(fileName: string, script: string, vite8Active: boolean): T {
            const scriptPath = path.join(tempDir, fileName);
            const outputPath = path.join(tempDir, `${fileName}.json`);
            fs.writeFileSync(scriptPath, script);

            // The output goes into a file, because the Vite config logs to stdout.
            execFileSync(process.execPath, [scriptPath, outputPath], {
                cwd: administrationRoot,
                env: {
                    ...process.env,
                    PROJECT_ROOT: createProjectRoot(vite8Active),
                    // Lets build/plugins.vite.ts build the extensions instead of writing the dev server files
                    VITE_MODE: 'production',
                    VITE_CONFIG_NATIVE_IGNORE_WARNING: 'true',
                },
                stdio: 'pipe',
            });

            return JSON.parse(fs.readFileSync(outputPath, 'utf8')) as T;
        }

        function resolveViteConfigScript(viteEntry: string, offendingPathPattern: string): string {
            return `
                import { registerHooks } from 'node:module';
                import { writeFileSync } from 'node:fs';

                const offendingPath = new RegExp(${JSON.stringify(offendingPathPattern)});
                const offending = new Set();

                registerHooks({
                    resolve(specifier, context, nextResolve) {
                        const result = nextResolve(specifier, context);

                        if (offendingPath.test(result.url)) {
                            offending.add(result.url);
                        }

                        return result;
                    },
                });

                const vite = await import(${JSON.stringify(viteEntry)});

                for (const command of ['serve', 'build']) {
                    await vite.resolveConfig({ configFile: ${JSON.stringify(viteConfigPath)}, logLevel: 'silent' }, command);
                }

                writeFileSync(process.argv[2], JSON.stringify({ offending: [...offending] }));
            `;
        }

        beforeAll(() => {
            tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'vite-toolchain-isolation-'));

            if (!fs.existsSync(viteServerMappingPath)) {
                fs.writeFileSync(viteServerMappingPath, JSON.stringify({ vitePort: 5173, extensions: {} }));
                createdViteServerMapping = true;
            }
        });

        afterAll(() => {
            fs.rmSync(tempDir, { recursive: true, force: true });

            if (createdViteServerMapping) {
                fs.rmSync(viteServerMappingPath, { force: true });
            }
        });

        it('does not load Vite 6 or its plugins when Vite 8 resolves the config', () => {
            const result = runScript<LoadedPaths>(
                'vite-8-config.mjs',
                resolveViteConfigScript(vite8Entry, vite6PathPattern),
                true,
            );

            expect(result.offending).toEqual([]);
        }, 120000);

        it('does not load the Vite 8 toolchain when Vite 6 resolves the config', () => {
            const result = runScript<LoadedPaths>(
                'vite-6-config.mjs',
                resolveViteConfigScript(vite6Entry, vite8PathPattern),
                false,
            );

            expect(result.offending).toEqual([]);
        }, 120000);

        it.each([
            { vite8Active: true, offendingPathPattern: vite6PathPattern, viteVersion: /^8\./ },
            { vite8Active: false, offendingPathPattern: vite8PathPattern, viteVersion: /^6\./ },
        ])(
            'loads only the matching toolchain in the ts-node process of the extensions (Vite 8: $vite8Active)',
            ({ vite8Active, offendingPathPattern, viteVersion }) => {
                const result = runScript<
                    LoadedPaths & { isVite8: boolean; viteVersion: string; exports: Record<string, string> }
                >(
                    `extensions-${vite8Active ? 'vite-8' : 'vite-6'}.cjs`,
                    `
                    const Module = require('module');
                    const { writeFileSync } = require('fs');

                    const offendingPath = new RegExp(${JSON.stringify(offendingPathPattern)});
                    const offending = new Set();
                    const resolveFilename = Module._resolveFilename;

                    Module._resolveFilename = function (...args) {
                        const resolved = resolveFilename.apply(this, args);

                        if (offendingPath.test(resolved)) {
                            offending.add(resolved);
                        }

                        return resolved;
                    };

                    require(${JSON.stringify(require.resolve('ts-node'))}).register({
                        transpileOnly: true,
                        project: ${JSON.stringify(path.join(administrationRoot, 'tsconfig.json'))},
                    });

                    require(${JSON.stringify(path.join(administrationRoot, 'build/plugins.vite.ts'))});

                    const { loadViteToolchain } = require(${JSON.stringify(path.join(__dirname, 'index.ts'))});

                    loadViteToolchain().then((toolchain) => {
                        process.on('exit', () => {
                            writeFileSync(process.argv[2], JSON.stringify({
                                offending: [...offending],
                                isVite8: toolchain.isVite8,
                                viteVersion: toolchain.vite.version,
                                exports: Object.fromEntries(
                                    Object.entries(toolchain).map(([name, value]) => [name, typeof value]),
                                ),
                            }));
                        });
                    });
                    `,
                    vite8Active,
                );

                expect(result.offending).toEqual([]);
                expect(result.isVite8).toBe(vite8Active);
                expect(result.viteVersion).toMatch(viteVersion);
                expect(result.exports).toEqual({
                    isVite8: 'boolean',
                    nodePolyfills: 'function',
                    symfonyPlugin: 'function',
                    vite: 'object',
                    vue: 'function',
                });
            },
            120000,
        );
    });
});
