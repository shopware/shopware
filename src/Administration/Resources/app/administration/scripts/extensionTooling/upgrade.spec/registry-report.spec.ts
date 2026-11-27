/**
 * @sw-package framework
 *
 * The codemod registry and how the report and `--list` render it.
 */

import path from 'path';
import { CODEMODS } from '../upgrade-codemods';
import type { Codemod, EslintCodemod } from '../upgrade-codemods';
import { renderCodemodList, renderUpgradeReport } from '../upgrade-report';
import type { CodemodReport, UpgradeExtensionsResult } from '../upgrade';

const ANSI = new RegExp(`${String.fromCharCode(27)}\\[[0-9;]*m`, 'g');
const plain = (text: string) => text.replace(ANSI, '');

const PLUGIN_DIRECTORIES: Record<string, string> = {
    'sw-core-rules': 'core-rules',
    'sw-deprecation-rules': 'deprecation-rules',
};

const commands = {
    setup: 'composer admin:setup-extension-tooling',
    generateSchema: 'composer admin:generate-entity-schema-types',
    check: 'composer admin:check-extensions',
    upgrade: 'composer admin:upgrade-extensions',
};

function eslintCodemod(overrides: Partial<EslintCodemod>): EslintCodemod {
    return {
        id: 'sample',
        title: 'Sample',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/sample',
        scope: 'script',
        ...overrides,
    };
}

function resultWith(reports: CodemodReport[], write: boolean): UpgradeExtensionsResult {
    return {
        target: '6.8',
        write,
        skipGitCheck: false,
        optIns: [],
        fatalDiagnostics: [],
        exitCode: 0,
        extensions: [
            {
                name: 'AcmeUpgrade',
                basePath: 'custom/plugins/AcmeUpgrade',
                codemods: reports,
                changedFiles: ['src/main.js'],
                warnings: [],
                errors: [],
            },
        ],
    };
}

describe('scripts/extensionTooling/upgrade registry', () => {
    const eslintCodemods = CODEMODS.filter((codemod): codemod is EslintCodemod => codemod.kind === 'eslint');

    it('runs every ESLint rule for one codemod only, because the runner keys config and report by rule', () => {
        const rules = eslintCodemods.map((codemod) => codemod.rule);

        expect(new Set(rules).size).toBe(rules.length);
    });

    it('uses unique ids', () => {
        const ids = CODEMODS.map((codemod) => codemod.id);

        expect(new Set(ids).size).toBe(ids.length);
    });

    it.each(eslintCodemods.map((codemod) => [codemod.id, codemod.rule]))('registers the rule of %s (%s)', (_id, rule) => {
        const [plugin, name] = rule.split('/');
        // eslint-disable-next-line @typescript-eslint/no-require-imports -- the rule plugins are CommonJS modules
        const index = require(path.resolve(__dirname, '../../../eslint-rules', PLUGIN_DIRECTORIES[plugin], 'index.js')) as {
            rules: Record<string, unknown>;
        };

        expect(index.rules[name]).toBeDefined();
    });
});

describe('scripts/extensionTooling/upgrade report', () => {
    const todo = eslintCodemod({ id: 'todo-sample', title: 'Removed things', action: 'todo' });
    const fix = eslintCodemod({ id: 'fix-sample', title: 'Renamed things', rule: 'sw-deprecation-rules/fix-sample' });
    const quiet = eslintCodemod({ id: 'quiet', title: 'Quiet', rule: 'sw-deprecation-rules/quiet' });
    const report = (codemod: Codemod, changes: number, manual = 0): CodemodReport => ({
        codemod,
        enabled: true,
        changes,
        files: changes > 0 ? 1 : 0,
        manual: Array.from({ length: manual }, (_, index) => ({
            file: 'src/main.js',
            line: index + 1,
            message: 'Check this.',
        })),
    });

    it('counts TODOs as spots in a dry run and folds codemods without findings into one line', () => {
        const output = plain(
            renderUpgradeReport(resultWith([report(todo, 2), report(fix, 1), report(quiet, 0)], false), commands),
        );

        expect(output).toContain('Removed things                      2 spots get a TODO');
        expect(output).toContain('Renamed things                      1 change in 1 file');
        expect(output).toContain('· 1 more codemods found nothing to change');
        expect(output).not.toContain('Quiet');
    });

    it('counts written TODOs after --write and lists them as manual work', () => {
        const output = plain(renderUpgradeReport(resultWith([report(todo, 1, 1)], true), commands));

        expect(output).toContain('Removed things                      1 TODO written, 1 spot needs manual work');
        expect(output).toContain('Needs manual work:');
    });

    it('says when no codemod found anything', () => {
        const output = plain(renderUpgradeReport(resultWith([report(quiet, 0)], false), commands));

        expect(output).toContain('· 1 codemods found nothing to change');
    });

    it('groups the codemods of a major in --list and keeps ungrouped ones at the end', () => {
        const output = plain(
            renderCodemodList([
                eslintCodemod({ id: 'a', title: 'First', group: 'Components' }),
                eslintCodemod({ id: 'b', title: 'Second', group: 'Twig blocks' }),
                eslintCodemod({ id: 'c', title: 'Third', group: 'Components' }),
                { id: 'sfc', title: 'Options API → SFC', target: '6.8', optIn: true, kind: 'sfc-migration' },
            ]),
        );

        // Every note starts in the same column, grouped or not.
        const row = (title: string, note: string) => `${title.padEnd(38, ' ')}${note}`;

        expect(output).toContain(
            [
                'Shopware 6.8',
                '  Components',
                row('    First', 'a'),
                row('    Third', 'c'),
                '  Twig blocks',
                row('    Second', 'b'),
                row('  Options API → SFC', 'opt-in, --with=sfc'),
            ].join('\n'),
        );
    });
});
