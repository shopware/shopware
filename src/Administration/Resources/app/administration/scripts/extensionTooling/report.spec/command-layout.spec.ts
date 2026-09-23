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
import { serializeBaseline } from '../baseline';
import { checkReport, extension, owned, project, run, setupReport, setupResult } from './helpers';

const FLEX_ADMIN_ROOT = '/shop/vendor/shopware/administration/Resources/app/administration';
const MONOREPO_ADMIN_ROOT = '/shop/src/Administration/Resources/app/administration';
const flexCommands = resolveToolingCommands('/shop', FLEX_ADMIN_ROOT);

describe('extension tooling command layout', () => {
    it('uses the composer scripts for a platform (monorepo) checkout', () => {
        expect(resolveToolingCommands('/shop', MONOREPO_ADMIN_ROOT)).toEqual(DEFAULT_TOOLING_COMMANDS);
        expect(DEFAULT_TOOLING_COMMANDS.check).toBe('composer admin:check-extensions');
    });

    it('uses bin/console for a Composer/Flex install (Administration under vendor/)', () => {
        expect(flexCommands).toEqual({
            check: 'bin/console administration:check-extensions',
            setup: 'bin/console administration:setup-extension-tooling',
            generateSchema: 'bin/console administration:generate-entity-schema-types',
        });
    });

    it('writes the layout-aware refresh command into the baseline marker', () => {
        expect(serializeBaseline({ version: 1, typescript: [], typescriptSpecs: [], eslint: [] }, flexCommands)).toContain(
            'refresh with bin/console administration:check-extensions -- --update-baseline',
        );
        // Default (no commands) keeps the composer form.
        expect(serializeBaseline({ version: 1, typescript: [], typescriptSpecs: [], eslint: [] })).toContain(
            'refresh with composer admin:check-extensions -- --update-baseline',
        );
    });

    it('renders the --fix baseline handoff with the layout-aware command', () => {
        const withFindings = extension(project('HasFindings'), { eslint: run('failed', { findings: 2 }) });
        const output = checkReport(
            { results: [withFindings], fatalDiagnostics: [], warnings: [], baselineUpdates: [], exitCode: 1 },
            { fix: true, commands: flexCommands },
        );

        expect(output).toContain('bin/console administration:check-extensions -- --update-baseline');
        expect(output).not.toContain('composer admin:check-extensions -- --update-baseline');
    });

    it('renders the setup next-step with the layout-aware command', () => {
        const needsBridge = project('NeedsBridge', {
            tsconfig: owned('custom/plugins/NeedsBridge/src/tsconfig.json', 'the extends chain does not reach the preset.'),
        });
        const output = setupReport(setupResult([needsBridge]), { commands: flexCommands });

        expect(output).toContain('bin/console administration:setup-extension-tooling');
        expect(output).not.toContain('composer admin:setup-extension-tooling');
    });
});
