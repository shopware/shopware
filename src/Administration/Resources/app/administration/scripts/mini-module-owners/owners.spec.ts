/**
 * @sw-package framework
 *
 * The owners of the mini-modules come from `mini-module-owners.json` only, so `.codeowners` must say the same.
 */

import fs from 'node:fs';
import path from 'node:path';
import { findMiniModules } from '../generate-shopware-modules/extract-modules';
import {
    CODEOWNERS_FILE,
    expectedCodeowners,
    findStatedOwner,
    readManifest,
    renderOwnersBlock,
    replaceOwnersBlock,
    REGENERATE_OWNERS_COMMAND,
} from './owners';

const administrationRoot = path.resolve(__dirname, '../..');
const ADMIN = 'src/Administration/Resources/app/administration';

describe('scripts/mini-module-owners', () => {
    it('has a .codeowners block that matches the manifest', () => {
        expect(
            fs.readFileSync(CODEOWNERS_FILE, 'utf8'),
            `The mini-module block of .codeowners is stale. Run \`${REGENERATE_OWNERS_COMMAND}\`.`,
        ).toBe(expectedCodeowners());
    });

    it('names only mini-modules in the manifest', () => {
        const miniModules = findMiniModules(administrationRoot);

        expect(
            Object.keys(readManifest()).filter((directory) => !miniModules.includes(directory)),
            'mini-module-owners.json names directories that are no mini-module',
        ).toEqual([]);
    });

    it('lists the owned files of each module, global.d.ts only where it exists, and keeps an optional owner optional', () => {
        // ARRANGE
        const manifest = {
            'src/module/sw-b': '@shopware/team-b',
            'src/module/sw-a': '? @shopware/team-a',
        };

        // ACT
        const block = renderOwnersBlock(manifest, (file) => file === 'src/module/sw-b/global.d.ts');

        // ASSERT
        expect(block.split('\n').filter((line) => !line.startsWith('#'))).toEqual([
            `? ${ADMIN}/src/module/sw-a/index.ts @shopware/team-a`,
            `? ${ADMIN}/src/module/sw-a/tests/requirements.spec.ts @shopware/team-a`,
            `? ${ADMIN}/src/module/sw-a/tests/regressions.spec.ts @shopware/team-a`,
            `? ${ADMIN}/src/module/sw-a/tests/public-api.spec.ts @shopware/team-a`,
            `${ADMIN}/src/module/sw-b/index.ts @shopware/team-b`,
            `${ADMIN}/src/module/sw-b/global.d.ts @shopware/team-b`,
            `${ADMIN}/src/module/sw-b/tests/requirements.spec.ts @shopware/team-b`,
            `${ADMIN}/src/module/sw-b/tests/regressions.spec.ts @shopware/team-b`,
            `${ADMIN}/src/module/sw-b/tests/public-api.spec.ts @shopware/team-b`,
        ]);
    });

    it('replaces the generated block and leaves the hand-written lines alone', () => {
        // ARRANGE
        const handWritten = '# product-operations\n.github/** @shopware/product-operations\n';
        const first = replaceOwnersBlock(
            handWritten,
            renderOwnersBlock({ 'src/a': '@shopware/a' }, () => false),
        );

        // ACT
        const second = replaceOwnersBlock(
            first,
            renderOwnersBlock({ 'src/b': '@shopware/b' }, () => false),
        );

        // ASSERT
        expect(second.startsWith(`${handWritten}\n# mini-modules: generated`)).toBe(true);
        expect(second).toContain(`${ADMIN}/src/b/index.ts @shopware/b`);
        expect(second).not.toContain('src/a/');
        expect(second.endsWith('# end of the generated mini-modules\n')).toBe(true);
    });

    describe('the owner .codeowners already states', () => {
        const codeowners = [
            `? ${ADMIN}/src/module/sw-media/** @shopware/product-cc-discovery`,
            `${ADMIN}/src/module/sw-flow/** @shopware/product-cc-after-sales`,
            `? ${ADMIN}/src/module/sw-flow/component/** @shopware/product-cc-checkout`,
            `& ${ADMIN}/src/module/sw-flow/component/sw-flow-x/** @shopware/product-cc-framework`,
            `${ADMIN}/src/module/sw-shared/** @shopware/team-a @shopware/team-b`,
        ].join('\n');

        it('keeps an optional owner optional', () => {
            // ACT
            const owner = findStatedOwner(codeowners, 'src/module/sw-media/component/sw-media-collapse');

            // ASSERT
            expect(owner).toBe('? @shopware/product-cc-discovery');
        });

        it('takes the last matching line, which wins, and ignores additional owners', () => {
            // ACT
            const owner = findStatedOwner(codeowners, 'src/module/sw-flow/component/sw-flow-x');

            // ASSERT
            expect(owner).toBe('? @shopware/product-cc-checkout');
        });

        it('states no owner when no line matches or the line names several teams', () => {
            // ACT
            const owners = [
                findStatedOwner(codeowners, 'src/module/sw-order/component/sw-order-x'),
                findStatedOwner(codeowners, 'src/module/sw-shared/component/sw-shared-x'),
            ];

            // ASSERT
            expect(owners).toEqual([
                null,
                null,
            ]);
        });

        it('ignores the generated block, which only repeats the manifest', () => {
            // ARRANGE
            const withBlock = replaceOwnersBlock(
                codeowners,
                renderOwnersBlock({ 'src/module/sw-order/component/sw-order-x': '@shopware/guess' }, () => false),
            );

            // ACT
            const owner = findStatedOwner(withBlock, 'src/module/sw-order/component/sw-order-x');

            // ASSERT
            expect(owner).toBeNull();
        });
    });
});
