/**
 * @sw-package framework
 */

/**
 * The re-export shim `--replace-originals` writes is the only file the codemod puts over a source
 * the developer already owns, and its docblock is the component's declared API surface. Both
 * annotations therefore have to survive the round trip exactly: reading either one from the wrong
 * part of the file publishes a private component or files it under the wrong package.
 */

import * as fs from 'fs';
import * as path from 'path';
import { runMigration } from './run-sfc-migration';
import { makeRoot, writeFile } from './spec-helpers';

const NAME = 'sw-shim-demo';

describe('scripts/codemods/sfc-migration/run-sfc-migration replacement shim docblock', () => {
    let tmpDir: string;

    /** Writes one registered component with the given `index.js`, migrates it, returns its shim. */
    const migrateWith = async (indexBody: string[]): Promise<string> => {
        writeFile(tmpDir, `${NAME}/index.js`, `${indexBody.join('\n')}\n`);
        writeFile(tmpDir, `${NAME}/${NAME}.html.twig`, '{% block sw_shim_demo %}\n    <p>x</p>\n{% endblock %}\n');
        writeFile(tmpDir, 'index.js', `Component.register('${NAME}', () => import('./${NAME}'));\n`);

        const result = await runMigration(tmpDir, { write: true, replaceOriginals: true });

        expect(result.reports).toMatchObject([{ name: NAME, outcome: 'full', registration: 'register' }]);

        return fs.readFileSync(path.join(tmpDir, NAME, 'index.js'), 'utf8');
    };

    beforeEach(() => {
        tmpDir = makeRoot('sfc-shim-');
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('keeps a private component private when a member documents itself as @public', async () => {
        const shim = await migrateWith([
            `import template from './${NAME}.html.twig';`,
            '',
            '/**',
            ' * @sw-package framework',
            ' *',
            ' * @private',
            ' */',
            'export default {',
            '    template,',
            '',
            '    props: {',
            '        /**',
            '         * @description The selector to append to',
            '         * @public',
            '         */',
            '        selector: {',
            '            type: String,',
            "            default: 'body',",
            '        },',
            '    },',
            '};',
        ]);

        expect(shim).toContain(' * @private');
        expect(shim).not.toContain('@public');
    });

    it('carries @public over when the component itself declares it', async () => {
        const shim = await migrateWith([
            `import template from './${NAME}.html.twig';`,
            '',
            '/**',
            ' * @public',
            ' * @sw-package framework',
            ' */',
            '// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations',
            'export default {',
            '    template,',
            '};',
        ]);

        expect(shim).toContain(' * @public');
        expect(shim).not.toContain('@private');
    });

    it('keeps a sub-package @sw-package value intact', async () => {
        const shim = await migrateWith([
            `import template from './${NAME}.html.twig';`,
            '',
            '/**',
            ' * @sw-package fundamentals@after-sales',
            ' *',
            ' * @private',
            ' */',
            'export default {',
            '    template,',
            '};',
        ]);

        expect(shim).toContain(' * @sw-package fundamentals@after-sales');
    });

    it('finds @sw-package in a file-level docblock above the imports', async () => {
        const shim = await migrateWith([
            '/**',
            ' * @sw-package checkout',
            ' */',
            '',
            `import template from './${NAME}.html.twig';`,
            '',
            '/**',
            ' * @private',
            ' */',
            'export default {',
            '    template,',
            '};',
        ]);

        expect(shim).toContain(' * @sw-package checkout');
        expect(shim).toContain(' * @private');
    });
});
