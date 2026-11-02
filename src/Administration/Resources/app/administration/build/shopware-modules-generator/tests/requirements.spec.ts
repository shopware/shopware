/**
 * @sw-package framework
 */

import fs from 'node:fs';
import path from 'node:path';
import { renderShopwareModules } from '../index';

const administrationRoot = path.resolve(__dirname, '../../..');

describe('build/shopware-modules-generator', () => {
    /**
     * @requirement What an extension may import is checked in, so a new utility, mixin or store shows up as a diff
     * in a pull request. That only holds while the checked-in files match the sources, so a stale file fails here
     * and names the command that fixes it.
     */
    it('keeps the checked-in registry and declarations in step with the Administration sources', () => {
        // ACT
        const files = renderShopwareModules(administrationRoot);

        // ASSERT
        expect(Object.keys(files)).toEqual([
            'shopware-modules.json',
            'src/shopware-virtual-modules.d.ts',
        ]);
        Object.entries(files).forEach(([file, content]) => {
            expect(
                fs.readFileSync(path.join(administrationRoot, file), 'utf8'),
                `${file} is stale. Run \`composer admin:generate-shopware-modules\`.`,
            ).toBe(content);
        });
    });
});
