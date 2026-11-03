/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { extractPublicModules } from '../src/extract-modules';

/** Writes a mini-module with the given `index.ts` into a temporary Administration and extracts it. */
function extractFrom(moduleDir: string, index: string): () => ReturnType<typeof extractPublicModules> {
    return () => {
        const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-mini-modules-'));

        fs.mkdirSync(path.join(root, moduleDir, 'src'), { recursive: true });
        fs.mkdirSync(path.join(root, moduleDir, 'tests'));
        fs.writeFileSync(path.join(root, moduleDir, 'index.ts'), index);

        try {
            return extractPublicModules(root);
        } finally {
            fs.rmSync(root, { recursive: true, force: true });
        }
    };
}

describe('build/shopware-modules-generator/src/extract-modules', () => {
    it('publishes a component module as a component without a kind tag', () => {
        const extract = extractFrom(
            'src/module/sw-example/component/sw-example-detail',
            "export default definePublicComponent({ name: 'sw-example-detail', load: () => import('./src/x') });\n",
        );

        expect(extract()['shopware:components'].subpaths).toEqual({ 'sw-example-detail': ['default'] });
    });

    it('publishes any other module under the kind its doc comment names', () => {
        const extract = extractFrom(
            'src/module/sw-example/helper/example-delivery',
            "/**\n * @shopware-kind helpers\n */\nexport { primaryDelivery } from './src/x';\n",
        );

        expect(extract()['shopware:helpers'].subpaths).toEqual({ 'example-delivery': ['primaryDelivery'] });
    });

    it('refuses a module that is no component and names no kind', () => {
        const extract = extractFrom(
            'src/module/sw-example/helper/example-delivery',
            "export { primaryDelivery } from './src/x';\n",
        );

        expect(extract).toThrow(
            '"src/module/sw-example/helper/example-delivery/index.ts" needs exactly one kind. Add "@shopware-kind <kind>"',
        );
    });

    it('refuses a kind that does not exist', () => {
        const extract = extractFrom(
            'src/module/sw-example/helper/example-delivery',
            "/** @shopware-kind gadgets */\nexport { primaryDelivery } from './src/x';\n",
        );

        expect(extract).toThrow('names the unknown kind "gadgets". Use one of: components, framework, handlers, helpers.');
    });

    it('refuses a component module whose directory is not named after the component', () => {
        const extract = extractFrom(
            'src/module/sw-example/component/sw-renamed',
            "export default definePublicComponent({ name: 'sw-example-detail', load: () => import('./src/x') });\n",
        );

        expect(extract).toThrow('"src/module/sw-example/component/sw-renamed" declares the component "sw-example-detail".');
    });
});
