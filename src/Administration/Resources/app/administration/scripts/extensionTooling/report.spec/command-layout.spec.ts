/**
 * @sw-package framework
 *
 * The printed next-step commands must match the reader's install layout: the
 * `composer admin:*` scripts in a platform checkout, but `bin/console
 * administration:*` in a Composer/Flex shop (where those composer scripts do
 * not exist). resolveToolingCommands picks the set; the renderers and the
 * baseline marker must honor it.
 */

import { DEFAULT_TOOLING_COMMANDS, resolveToolingCommands } from '../shared';
import { project, resolution, setupReport, setupResult } from './helpers';

const FLEX_ADMIN_ROOT = '/shop/vendor/shopware/administration/Resources/app/administration';
const MONOREPO_ADMIN_ROOT = '/shop/src/Administration/Resources/app/administration';
const flexCommands = resolveToolingCommands('/shop', FLEX_ADMIN_ROOT);

describe('extension tooling command layout', () => {
    it('uses the composer scripts for a platform (monorepo) checkout', () => {
        expect(resolveToolingCommands('/shop', MONOREPO_ADMIN_ROOT)).toEqual(DEFAULT_TOOLING_COMMANDS);
        expect(DEFAULT_TOOLING_COMMANDS.setup).toBe('composer admin:setup-extension-tooling');
    });

    it('uses bin/console for a Composer/Flex install (Administration under vendor/)', () => {
        expect(flexCommands).toEqual({
            setup: 'bin/console administration:setup-extension-tooling',
            generateSchema: 'bin/console administration:generate-entity-schema-types',
        });
    });

    it('renders the not-bridged next-step with the layout-aware command', () => {
        const needsBridge = project('NeedsBridge', {
            tsconfig: 'custom/plugins/NeedsBridge/src/tsconfig.json',
            ts: resolution('unmanaged', { reason: 'not-extending' }),
        });
        const output = setupReport(setupResult([needsBridge]), { commands: flexCommands });

        expect(output).toContain('bin/console administration:setup-extension-tooling');
        expect(output).not.toContain('composer admin:setup-extension-tooling');
    });
});
