/**
 * @sw-package framework
 *
 * What the setup report must say: the coverage buckets and their single action,
 * the change list with its ownership labels (real run and dry run), the
 * project-root projection block, the experimental notice on every branch, the
 * empty state, and the layout-aware commands.
 */

import { DEFAULT_TOOLING_COMMANDS, resolveToolingCommands } from '../shared';
import { owned, project, setupReport, setupResult } from './helpers';

const FLEX_COMMANDS = resolveToolingCommands('/shop', '/shop/vendor/shopware/administration/Resources/app/administration');

describe('scripts/extensionTooling/report renderSetupReport', () => {
    const managed = project('FroshTools');
    const needsBridge = project('SwagPayPal', {
        tsconfig: owned('custom/plugins/SwagPayPal/src/Resources/app/administration/tsconfig.json', 'why it drifts'),
        eslintConfig: owned('custom/plugins/SwagPayPal/src/Resources/app/administration/eslint.config.mjs', 'and why'),
    });
    const bridged = project('Bridged', {
        bridgePresent: true,
        tsconfig: owned('custom/plugins/Bridged/src/Resources/app/administration/tsconfig.json'),
        eslintConfig: owned('custom/plugins/Bridged/src/Resources/app/administration/eslint.config.mjs'),
    });
    // Its tsconfig already extends the bridge; an own "files" array is what
    // breaks composition, so its remediation must not mention "extends".
    const unwired = project('Unwired', {
        bridgePresent: true,
        tsconfig: owned(
            'custom/plugins/Unwired/src/Resources/app/administration/tsconfig.json',
            'own "files" array',
            'files-override',
        ),
        eslintConfig: owned('custom/plugins/Unwired/src/Resources/app/administration/eslint.config.mjs'),
    });
    // Same bucket, the opposite defect: no extends at all, and an eslint config
    // that never composes the factory.
    const unwiredNoExtends = project('UnwiredBare', {
        bridgePresent: true,
        tsconfig: owned(
            'custom/plugins/UnwiredBare/src/Resources/app/administration/tsconfig.json',
            'the extends chain does not reach the preset',
        ),
        eslintConfig: owned(
            'custom/plugins/UnwiredBare/src/Resources/app/administration/eslint.config.mjs',
            'the config does not compose the factory',
            'factory-missing',
        ),
    });

    it('classifies every bucket and gives each uncovered one its action', () => {
        const output = setupReport(
            setupResult([
                managed,
                needsBridge,
                bridged,
                unwired,
                unwiredNoExtends,
            ]),
        );

        expect(output).toContain('✔ ready');
        expect(output).toContain('● bridged');
        expect(output).toContain('⚠ bridge unwired');
        expect(output).toContain('⚠ not bridged');
        // The copy-pasteable remediation is the actual user contract here.
        expect(output).toContain('"extends": "./.shopware/tsconfig.json"');
        expect(output).toContain("import shopware from './.shopware/eslint.mjs'");
        expect(output).toContain('bridges are generated automatically');
        // The per-config drift detail names the trap the bucket note cannot.
        expect(output).toContain('Unwired: own "files" array');
        expect(output).not.toContain('--shim');
    });

    // The reported bug: one bucket-wide "add extends" action was printed for
    // every unwired extension, including the ones whose extends was already
    // there and whose real problem was an own "files" array.
    it('gives each unwired config the fix for its own defect', () => {
        const output = setupReport(
            setupResult([
                unwired,
                unwiredNoExtends,
            ]),
        );
        const unwiredBlock = output.slice(output.indexOf('Unwired: own "files" array'), output.indexOf('UnwiredBare:'));

        expect(unwiredBlock).toContain('remove the own "files" array');
        expect(unwiredBlock).not.toContain('add "extends"');

        const bareBlock = output.slice(output.indexOf('UnwiredBare:'));

        expect(bareBlock).toContain('add "extends": "./.shopware/tsconfig.json"');
        expect(bareBlock).toContain("import shopware from './.shopware/eslint.mjs'");
    });

    it('gives a fully composing extension no action lines', () => {
        const output = setupReport(setupResult([bridged]));

        expect(output).toContain('● bridged  Bridged');
        expect(output).not.toContain('→');
    });

    it('marks the extensions this run freshly bridged', () => {
        const output = setupReport(
            setupResult([bridged], {
                changed: true,
                writes: [
                    {
                        file: 'custom/plugins/Bridged/src/Resources/app/administration/.shopware/tsconfig.json',
                        state: 'created',
                    },
                ],
            }),
        );

        expect(output).toContain('Bridged (new)');
        // An idempotent re-run creates nothing, so nothing is marked.
        expect(setupReport(setupResult([bridged]))).not.toContain('(new)');
    });

    it('adds the local-lifecycle note for vendor extensions', () => {
        const output = setupReport(
            setupResult([
                project('Acme', {
                    vendor: true,
                    basePath: 'vendor/acme/admin',
                    sourcePath: 'vendor/acme/admin/src/Resources/app/administration/src',
                    bridgePresent: true,
                    tsconfig: owned('vendor/acme/admin/src/Resources/app/administration/tsconfig.json', 'drift'),
                }),
            ]),
        );

        expect(output).toContain('composer update removes them');
    });

    it('labels changed files by ownership and summarizes the bridge files', () => {
        const result = setupResult([project('Mono')], {
            changed: true,
            writes: [
                { file: 'custom/plugins/Mono/.shopware/tsconfig.json', state: 'created' },
                { file: 'custom/plugins/Mono/.shopware/eslint.mjs', state: 'created' },
                { file: 'custom/plugins/Mono/tsconfig.json', state: 'created' },
                { file: 'vendor/acme/admin/src/Resources/app/administration/tsconfig.json', state: 'created' },
                { file: 'tsconfig.json', state: 'updated' },
                { file: '.zed/settings.json', state: 'created' },
                { file: 'var/admin-extension-tooling/manifest.json', state: 'updated' },
            ],
        });
        const output = setupReport(result);

        expect(output).toContain('generated: custom/plugins/Mono/tsconfig.json [commit this]');
        expect(output).toContain(
            'generated: vendor/acme/admin/src/Resources/app/administration/tsconfig.json ' +
                '[local — restored by re-running setup]',
        );
        // A bare filename is the project-root projection, not a committable
        // plugin config — the label is what keeps the two apart in the list.
        expect(output).toContain('updated: tsconfig.json [project-root projection — git-ignored]');
        expect(output).not.toContain('updated: tsconfig.json [commit this]');
        // No listed line may stay unlabeled, or it reads as unclassified.
        expect(output).toContain('generated: .zed/settings.json [disposable — regenerated by setup]');
        expect(output).toContain('updated: var/admin-extension-tooling/manifest.json [disposable — regenerated by setup]');
        // Bridge files scale with the extension count, so they are counted.
        expect(output).toContain('2 git-ignored .shopware/ bridge file(s) — never commit');
        expect(output).not.toContain('custom/plugins/Mono/.shopware/tsconfig.json');
    });

    it('switches the change list to the dry-run wording under --check', () => {
        const result = setupResult([project('Mono')], {
            changed: true,
            writes: [{ file: 'custom/plugins/Mono/tsconfig.json', state: 'created' }],
            staleFiles: ['var/admin-extension-tooling/manifest.json'],
        });
        const output = setupReport(result, { checkOnly: true });

        expect(output).toContain('Setup is stale');
        expect(output).toContain('would create: custom/plugins/Mono/tsconfig.json [commit this]');
        expect(output).toContain('would delete: var/admin-extension-tooling/manifest.json');
        // Nothing was generated, so the real-run verb must not appear.
        expect(output).not.toContain('generated:');
    });

    it('reads "Configs up to date" when nothing changed', () => {
        expect(setupReport(setupResult([managed]))).toContain('Configs up to date');
    });

    // The change list only shows what a run touched, so on an idempotent re-run
    // it collapses to "Configs up to date" and would never name the generated
    // project-root configs. This block reads the manifest instead, so their
    // existence is stated on every run.
    describe('project-root projection block', () => {
        it('names the generated root configs even when the run changed nothing', () => {
            const output = setupReport(setupResult([managed]));

            expect(output).toContain('Configs up to date');
            expect(output).toContain('project-root projection  tsconfig.json, eslint.config.mjs');
            expect(output).toContain('git-ignored — never commit or hand-edit');
        });

        it('states the roots each file covers separately, because the two differ', () => {
            // managed owns no config, so the root tsconfig is what type-checks it.
            const output = setupReport(setupResult([managed]));

            expect(output).toContain('tsconfig.json — type-checks the 1 source root(s) no extension config governs');
            expect(output).toContain('eslint.config.mjs — scopes ESLint to 1 source root(s)');
        });

        it('spells out the fully-bridged steady state instead of printing a bare zero', () => {
            const output = setupReport(setupResult([bridged]));

            expect(output).toContain('currently none, every root has its own');
            expect(output).not.toContain('0 source root(s)');
        });

        it('omits a root config that was never written', () => {
            const skipped = setupResult([managed]);
            skipped.manifest.rootConfigs.eslintConfig = 'skipped';

            const output = setupReport(skipped);

            expect(output).toContain('project-root projection  tsconfig.json  ');
            expect(output).not.toContain('scopes ESLint to');
        });

        it('leaves a user-owned root config to its warning instead of claiming it', () => {
            const conflicted = setupResult([managed]);
            conflicted.manifest.rootConfigs.tsconfig = 'conflict';

            const output = setupReport(conflicted);

            expect(output).toContain('root tsconfig.json is user-owned');
            expect(output).toContain('project-root projection  eslint.config.mjs  ');
            expect(output).not.toContain('tsconfig.json — type-checks');
        });

        it('stays out of the empty state, which is a discovery message', () => {
            expect(setupReport(setupResult([]))).not.toContain('project-root projection');
        });
    });

    it('states the experimental status on every branch, including the empty state', () => {
        // The report is the only surface a developer is guaranteed to see, so the
        // BC caveat may never depend on which branch the run takes.
        expect(setupReport(setupResult([managed]))).toContain(
            'EXPERIMENTAL — not covered by the backwards-compatibility promise.',
        );
        expect(setupReport(setupResult([managed]))).toContain('manifest');
        expect(setupReport(setupResult([]))).toContain('EXPERIMENTAL');
        expect(setupReport(setupResult([managed]), { checkOnly: true })).toContain('EXPERIMENTAL');
    });

    it('replaces the empty state with discovery guidance instead of a green "up to date"', () => {
        const output = setupReport(setupResult([]));

        expect(output).toContain('no extensions found');
        expect(output).toContain('bin/console bundle:dump');
        expect(output).not.toContain('0 extension(s)');
    });

    it('lists platform bundles in their own bucket, excluded from the count', () => {
        const storefront = project('Storefront', {
            basePath: 'src/Storefront',
            eslintConfig: owned('src/Storefront/Resources/app/administration/eslint.config.mjs', 'drift'),
        });
        const output = setupReport(
            setupResult([
                managed,
                storefront,
            ]),
        );

        expect(output).toContain('— 1 extension(s)');
        expect(output).toContain('platform  Storefront');
        // Platform bundles never get an action: the core toolchain checks them.
        expect(output).not.toContain('Storefront: drift');

        const emptyWithPlatform = setupReport(setupResult([storefront]));

        expect(emptyWithPlatform).toContain('no extensions found');
    });

    it('renders the IDE / integration instruction block the run produced', () => {
        const withInstructions = setupResult([managed], {
            instructions: ['PhpStorm (configure once, Settings → Languages & Frameworks): …'],
        });

        expect(setupReport(withInstructions)).toContain('Settings → Languages');
        expect(setupReport(setupResult([managed]))).not.toContain('Settings → Languages');
    });

    it('warns when a root projection is user-owned', () => {
        const conflicted = setupResult([managed]);
        conflicted.manifest.rootConfigs.tsconfig = 'conflict';

        expect(setupReport(conflicted)).toContain('root tsconfig.json is user-owned');
    });

    it('uses the layout-aware commands for actions and the flag hint', () => {
        expect(DEFAULT_TOOLING_COMMANDS.setup).toBe('composer admin:setup-extension-tooling');
        expect(FLEX_COMMANDS.setup).toBe('bin/console administration:setup-extension-tooling');

        const output = setupReport(setupResult([needsBridge]), { commands: FLEX_COMMANDS, showFlagHint: true });

        expect(output).toContain('bin/console administration:setup-extension-tooling');
        expect(output).not.toContain('composer admin:setup-extension-tooling');
        expect(output).toContain('Options need "--"');
    });
});
