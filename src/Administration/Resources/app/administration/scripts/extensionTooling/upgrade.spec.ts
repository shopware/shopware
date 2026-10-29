/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { runUpgradeCli, upgradeExtensions } from './upgrade';
import { CodemodSelectionError } from './upgrade-codemods';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from './test-helpers';

jest.setTimeout(120_000);

const PLUGIN = 'custom/plugins/AcmeUpgrade';
const SOURCE = `${PLUGIN}/src/Resources/app/administration/src`;

function useRealToolchain(administrationRoot: string): void {
    for (const toolingFile of fs.readdirSync(path.join(realAdministrationRoot, 'extension-tooling'))) {
        fs.copyFileSync(
            path.join(realAdministrationRoot, 'extension-tooling', toolingFile),
            path.join(administrationRoot, 'extension-tooling', toolingFile),
        );
    }

    fs.rmSync(path.join(administrationRoot, 'node_modules'), { recursive: true, force: true });
    fs.symlinkSync(path.join(realAdministrationRoot, 'node_modules'), path.join(administrationRoot, 'node_modules'), 'dir');
}

function writePlugin(projectRoot: string, pluginPath = PLUGIN): void {
    const source = `${pluginPath}/src/Resources/app/administration/src`;

    writeFile(path.join(projectRoot, pluginPath, 'composer.json'), '{}\n');
    writeFile(path.join(projectRoot, source, 'main.js'), [
        "const pages = import.meta.glob('./page/**/!(*.spec).js', { eager: true });",
        "const blocks = import.meta.glob('./**/@(index|main).js');",
        '',
        'export { pages, blocks };',
    ]);
    writeFile(path.join(projectRoot, source, 'page/acme-list/index.js'), [
        'export default {',
        '    computed: {',
        '        title() {',
        "            return this.$tc('acme.list.title');",
        '        },',
        '    },',
        '};',
    ]);
    writeFile(path.join(projectRoot, source, 'page/acme-list/acme-list.html.twig'), [
        '{% block acme_list %}',
        '<sw-button variant="primary">',
        "    {{ $t('acme.list.save') }}",
        '</sw-button>',
        '{% endblock %}',
    ]);
    writeFile(path.join(projectRoot, source, 'component/acme-card/index.js'), [
        "import template from './acme-card.html.twig';",
        '',
        'export default {',
        '    template,',
        '    props: {',
        '        title: { type: String, required: true },',
        '    },',
        '};',
    ]);
    writeFile(path.join(projectRoot, source, 'component/acme-card/acme-card.html.twig'), [
        '{% block acme_card %}',
        '<div class="acme-card">{{ title }}</div>',
        '{% endblock %}',
    ]);
}

function readSource(projectRoot: string, file: string): string {
    return fs.readFileSync(path.join(projectRoot, SOURCE, file), 'utf8');
}

function commitAll(directory: string): void {
    const git = (...args: string[]) =>
        spawnSync(
            'git',
            [
                '-c',
                'user.name=Test',
                '-c',
                'user.email=test@example.com',
                ...args,
            ],
            { cwd: directory },
        );

    git('init', '-q');
    git('add', '-A');
    git('commit', '-qm', 'initial');
}

describe('scripts/extensionTooling/upgrade', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-upgrade-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        useRealToolchain(administrationRoot);
        writePlugin(projectRoot);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'AcmeUpgrade',
                basePath: `${PLUGIN}/src`,
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        commitAll(path.join(projectRoot, PLUGIN));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('reports what each codemod would change without writing anything', async () => {
        const before = readSource(projectRoot, 'page/acme-list/acme-list.html.twig');

        const result = await upgradeExtensions({ projectRoot, administrationRoot });
        const [extension] = result.extensions;
        const report = (id: string) => extension.codemods.find((candidate) => candidate.codemod.id === id);

        expect(result.target).toBe('6.8');
        expect(result.exitCode).toBe(0);
        expect(report('mt-components')).toMatchObject({ changes: 1, files: 1 });
        expect(report('tc-translation')).toMatchObject({ changes: 1, files: 1 });
        expect(report('vite-8-glob')).toMatchObject({ changes: 1, files: 1 });
        expect(report('vite-8-glob')?.manual).toEqual([
            expect.objectContaining({ file: 'src/Resources/app/administration/src/main.js', line: 2 }),
        ]);
        expect(report('sfc-migration')).toMatchObject({ enabled: false, changes: 1 });
        expect(extension.changedFiles).toEqual([
            'src/Resources/app/administration/src/main.js',
            'src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
            'src/Resources/app/administration/src/page/acme-list/index.js',
        ]);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toBe(before);
    });

    it('counts a file once when several codemods change it', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'page/acme-list/acme-list.html.twig'), [
            '{% block acme_list %}',
            '<sw-button :label="$tc(\'acme.list.save\')" />',
            '{% endblock %}',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));

        const result = await upgradeExtensions({ projectRoot, administrationRoot, target: '6.8', write: true });

        expect(result.extensions[0].changedFiles).toContain(
            'src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
        );
        expect(result.extensions[0].changedFiles).toHaveLength(3);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain(
            '<mt-button :label="$t(\'acme.list.save\')"',
        );
    });

    it('writes the fixes and keeps what needs a human for the report', async () => {
        const result = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(result.exitCode).toBe(0);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
        expect(readSource(projectRoot, 'page/acme-list/index.js')).toContain("this.$t('acme.list.title')");
        expect(readSource(projectRoot, 'main.js')).toContain(
            "import.meta.glob(['./page/**/*.js', '!./page/**/*.spec.js'], { eager: true })",
        );
        expect(readSource(projectRoot, 'main.js')).toContain("import.meta.glob('./**/@(index|main).js')");
        expect(result.extensions[0].codemods.find((report) => report.codemod.id === 'vite-8-glob')?.manual).toHaveLength(1);
        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/acme-card.vue'))).toBe(false);
    });

    it('converts components to SFCs only when the opt-in codemod is requested', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, write: true, optIns: ['sfc-migration'] });

        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/acme-card.vue'))).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/index.js'))).toBe(true);
    });

    it('applies only the codemods up to the target', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, target: '6.7', write: true });

        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
        expect(readSource(projectRoot, 'page/acme-list/index.js')).toContain('this.$tc(');
    });

    it('refuses to write into an extension with uncommitted changes', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'wip.js'), ['export {};']);

        const result = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(result.exitCode).toBe(1);
        expect(result.fatalDiagnostics.join('\n')).toContain(`${PLUGIN} has uncommitted changes`);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');
    });

    it('refuses to write into an extension git cannot restore unless the check is skipped', async () => {
        fs.rmSync(path.join(projectRoot, PLUGIN, '.git'), { recursive: true, force: true });

        const refused = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(refused.exitCode).toBe(1);
        expect(refused.fatalDiagnostics.join('\n')).toContain(`${PLUGIN} is not tracked by git`);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');

        const forced = await upgradeExtensions({ projectRoot, administrationRoot, write: true, skipGitCheck: true });

        expect(forced.exitCode).toBe(0);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
    });

    it('does not fail the run when the SFC probe hits a component nobody asked to convert', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'component/acme-broken/index.js'), [
            'export default {',
            '    render() {',
            '        return <div />;',
            '    },',
            '};',
        ]);

        const result = await upgradeExtensions({ projectRoot, administrationRoot });

        expect(result.exitCode).toBe(0);
        expect(result.extensions[0].errors).toEqual([]);
    });

    it('upgrades static plugins like any other project extension', async () => {
        writePlugin(projectRoot, 'custom/static-plugins/AcmeStatic');
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'AcmeStatic',
                basePath: 'custom/static-plugins/AcmeStatic/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = await upgradeExtensions({ projectRoot, administrationRoot });

        expect(result.extensions.map((extension) => extension.name)).toEqual(['AcmeStatic']);
        expect(result.extensions[0].skipped).toBeUndefined();
    });

    it('leaves vendor extensions alone unless they are named via --only', async () => {
        writePlugin(projectRoot, 'vendor/acme/vendor-upgrade');
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'VendorUpgrade',
                basePath: 'vendor/acme/vendor-upgrade/src',
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        commitAll(path.join(projectRoot, 'vendor/acme/vendor-upgrade'));
        const vendorTemplate = path.join(
            projectRoot,
            'vendor/acme/vendor-upgrade/src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
        );

        const implicit = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(implicit.extensions[0].skipped).toContain('vendor-installed');
        expect(fs.readFileSync(vendorTemplate, 'utf8')).toContain('<sw-button');

        await upgradeExtensions({
            projectRoot,
            administrationRoot,
            only: ['vendor-upgrade'],
            explicitOnly: ['vendor-upgrade'],
            write: true,
        });

        expect(fs.readFileSync(vendorTemplate, 'utf8')).toContain('<mt-button');
    });

    it('rejects targets and opt-in codemods this Administration does not ship', async () => {
        await expect(upgradeExtensions({ projectRoot, administrationRoot, target: '6.9' })).rejects.toThrow(
            new CodemodSelectionError(
                'This Administration ships codemods up to 6.8. Update Shopware to get the codemods for 6.9, then run this again.',
            ),
        );
        await expect(upgradeExtensions({ projectRoot, administrationRoot, optIns: ['unknown'] })).rejects.toThrow(
            '--with names unknown codemod(s): unknown',
        );
        await expect(
            upgradeExtensions({ projectRoot, administrationRoot, target: '6.7', optIns: ['sfc-migration'] }),
        ).rejects.toThrow('--with=sfc-migration needs --target=6.8 or newer.');
    });

    describe('CLI', () => {
        let output: string[];

        beforeEach(() => {
            output = [];
            jest.spyOn(console, 'log').mockImplementation((line: string) => output.push(line));
            jest.spyOn(console, 'error').mockImplementation((line: string) => output.push(line));
        });

        afterEach(() => {
            jest.restoreAllMocks();
        });

        it('prints the dry-run report with the command that applies it', async () => {
            const exitCode = await runUpgradeCli([
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
                '--only=AcmeUpgrade',
            ]);
            const report = output.join('\n');

            expect(exitCode).toBe(0);
            expect(report).toContain('Dry run, nothing was written.');
            expect(report).toContain(
                'bin/console administration:upgrade-extensions -- --only=AcmeUpgrade --target=6.8 --write',
            );
        });

        it('exits with a usage error for an unknown target', async () => {
            const exitCode = await runUpgradeCli([
                `--project-root=${projectRoot}`,
                `--administration-root=${administrationRoot}`,
                '--only=AcmeUpgrade',
                '--target=7.0',
            ]);

            expect(exitCode).toBe(2);
            expect(output.join('\n')).toContain('ships codemods up to 6.8');
        });

        it('lists the codemods per target', async () => {
            expect(await runUpgradeCli(['--list'])).toBe(0);
            expect(output.join('\n')).toMatch(/Shopware 6\.8[\s\S]*Options API → SFC\s+opt-in, --with=sfc-migration/);
        });
    });
});
