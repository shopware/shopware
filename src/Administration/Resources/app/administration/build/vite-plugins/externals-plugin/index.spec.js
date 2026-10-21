/**
 * @sw-package framework
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import ExternalsPlugin from './index';

describe('build/vite-plugins/externals-plugin', () => {
    it('should be a function with 0 arguments', () => {
        expect(typeof ExternalsPlugin).toBe('function');

        // check that the function has 0 arguments
        expect(ExternalsPlugin.length).toBe(0);
    });

    it('should return an object with a name and config property', () => {
        const plugin = ExternalsPlugin();

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-vite-plugin-vue-globals');

        // Check if the plugin has a transform method
        expect(plugin).toHaveProperty('config');
    });

    it('should add vue alias for config without own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = { resolve: {} };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult.length).toBe(1);

        const vueAlias = aliasResult[0];
        expect(vueAlias.find).toStrictEqual(/^vue$/);
        expect(vueAlias.replacement.endsWith('node_modules/.shopware-vite-plugin-vue-globals/vue.js')).toBe(true);
    });

    it('should add vue alias for config with own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = {
            resolve: {
                alias: [
                    {
                        find: /@/,
                        replacement: './src/',
                    },
                ],
            },
        };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult.length).toBe(2);

        const srcAlias = aliasResult[0];
        expect(srcAlias.find).toStrictEqual(/@/);
        expect(srcAlias.replacement).toBe('./src/');

        const vue = aliasResult[1];
        expect(vue.find).toStrictEqual(/^vue$/);
        expect(vue.replacement.endsWith('node_modules/.shopware-vite-plugin-vue-globals/vue.js')).toBe(true);
    });

    describe('the shared vue.js file', () => {
        const vueJsContent = "module.exports = window['Shopware']?.['Vue']";
        let cwd;
        let vueJsPath;

        beforeEach(() => {
            cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'externals-plugin-'));
            vueJsPath = path.join(cwd, 'node_modules', '.shopware-vite-plugin-vue-globals', 'vue.js');
            jest.spyOn(process, 'cwd').mockReturnValue(cwd);
        });

        afterEach(() => {
            jest.restoreAllMocks();
            fs.rmSync(cwd, { recursive: true, force: true });
        });

        it('should create the file that exports the global Vue instance', async () => {
            const result = await ExternalsPlugin().config({ resolve: {} });

            expect(result.resolve.alias[0].replacement).toBe(vueJsPath);
            expect(fs.readFileSync(vueJsPath, 'utf-8')).toBe(vueJsContent);
        });

        it('should keep the file and its directory of other dev servers untouched', async () => {
            await ExternalsPlugin().config({ resolve: {} });
            const unchangedTime = new Date('2020-01-01T00:00:00Z');
            fs.utimesSync(vueJsPath, unchangedTime, unchangedTime);
            const otherFilePath = path.join(path.dirname(vueJsPath), 'other.js');
            fs.writeFileSync(otherFilePath, 'other');

            const result = await ExternalsPlugin().config({ resolve: {} });

            expect(result.resolve.alias[0].replacement).toBe(vueJsPath);
            expect(fs.readFileSync(vueJsPath, 'utf-8')).toBe(vueJsContent);
            expect(fs.statSync(vueJsPath).mtime).toEqual(unchangedTime);
            expect(fs.existsSync(otherFilePath)).toBe(true);
        });

        it('should replace an outdated file', async () => {
            fs.mkdirSync(path.dirname(vueJsPath), { recursive: true });
            fs.writeFileSync(vueJsPath, 'export default window.Vue');

            await ExternalsPlugin().config({ resolve: {} });

            expect(fs.readFileSync(vueJsPath, 'utf-8')).toBe(vueJsContent);
        });
    });
});
