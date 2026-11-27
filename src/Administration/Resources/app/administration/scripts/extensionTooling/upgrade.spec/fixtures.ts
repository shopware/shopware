/**
 * @sw-package framework
 *
 * Shared fixtures for the upgrade specs. `createUpgradeProject` builds a temp
 * project with the real codemod toolchain and one committed plugin whose
 * sources every shipped codemod has something to rewrite in.
 *
 * Not a spec file: no `.spec.ts` suffix, so Jest does not execute it.
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import {
    createSkeletonAdmin,
    createTempProject,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

export const PLUGIN = 'custom/plugins/AcmeUpgrade';
export const SOURCE = `${PLUGIN}/src/Resources/app/administration/src`;

export interface UpgradeFixtureProject {
    projectRoot: string;
    administrationRoot: string;
}

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

export function writePlugin(projectRoot: string, pluginPath = PLUGIN): void {
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

export function readSource(projectRoot: string, file: string): string {
    return fs.readFileSync(path.join(projectRoot, SOURCE, file), 'utf8');
}

export function commitAll(directory: string): void {
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

export function createUpgradeProject(prefix: string): UpgradeFixtureProject {
    const projectRoot = createTempProject(prefix);
    const administrationRoot = createSkeletonAdmin(projectRoot);

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

    return { projectRoot, administrationRoot };
}
