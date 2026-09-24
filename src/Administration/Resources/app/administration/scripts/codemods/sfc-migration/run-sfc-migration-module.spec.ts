/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as path from 'path';
import { runMigration } from './run-sfc-migration';
import { makeRoot, manifest, writeFile } from './spec-helpers';

const NAME = 'sw-module-demo';
const MODULE_FILE = `${NAME}/${NAME}.module.js`;

describe('scripts/codemods/sfc-migration/run-sfc-migration sibling module', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = makeRoot('sfc-module-');
        writeFile(
            tmpDir,
            `${NAME}/index.js`,
            [
                `import template from './${NAME}.html.twig';`,
                '',
                '/**',
                ' * @sw-package framework',
                ' */',
                'const cache = new Map();',
                '',
                'export default {',
                '    template,',
                '    methods: {',
                '        size() {',
                '            return cache.size;',
                '        },',
                '    },',
                '};',
                '',
            ].join('\n'),
        );
        writeFile(tmpDir, `${NAME}/${NAME}.html.twig`, '{% block sw_module_demo %}\n    <p>x</p>\n{% endblock %}\n');
        writeFile(tmpDir, 'index.js', `Component.register('${NAME}', () => import('./${NAME}'));\n`);
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('writes the module-level code next to the SFC that imports it', async () => {
        const result = await runMigration(tmpDir, { write: true });

        expect(result.reports).toMatchObject([{ name: NAME, outcome: 'full', reasons: [] }]);
        expect(fs.readFileSync(path.join(tmpDir, `${NAME}/${NAME}.vue`), 'utf8')).toContain(
            `import { cache } from './${NAME}.module';`,
        );
        expect(fs.readFileSync(path.join(tmpDir, MODULE_FILE), 'utf8')).toBe(
            [
                '/**',
                ' * @sw-package framework',
                ' */',
                '',
                'const cache = new Map();',
                '',
                '/**',
                ' * @private',
                ' */',
                'export { cache };',
                '',
            ].join('\n'),
        );
    });

    it('writes nothing in a dry run', async () => {
        const before = manifest(tmpDir);

        await runMigration(tmpDir);

        expect(manifest(tmpDir)).toEqual(before);
    });

    it('skips the component instead of overwriting an existing file of the sibling module name', async () => {
        writeFile(tmpDir, MODULE_FILE, 'export const unrelated = 1;\n');

        const result = await runMigration(tmpDir, { write: true });

        expect(result.reports).toMatchObject([
            {
                name: NAME,
                outcome: 'skipped',
                reasons: [`${NAME}.module.js already exists, so the module-level code has nowhere to go`],
            },
        ]);
        expect(fs.existsSync(path.join(tmpDir, `${NAME}/${NAME}.vue`))).toBe(false);
        expect(fs.readFileSync(path.join(tmpDir, MODULE_FILE), 'utf8')).toBe('export const unrelated = 1;\n');
    });
});
