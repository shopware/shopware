/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { createAnalysisContext, type AnalysisContext } from '../analysis-context';
import type { ComponentEntry } from '../types';
import { extractComponents, type ComponentExtraction, type ComponentExtractionState } from '.';

const FIXTURE_ROOT = path.join(__dirname, '__fixtures__', 'reuse-root');
const ADMIN_NODE_MODULES = path.resolve(__dirname, '../../../../node_modules');
const MODULE_DIR = 'src/module/sw-reuse';
const TYPED_DIR = `${MODULE_DIR}/component/sw-reuse-typed`;
const PLAIN_DIR = `${MODULE_DIR}/component/sw-reuse-plain`;
const OVERRIDE_DIR = `${MODULE_DIR}/extension/sw-reuse-plain`;

describe('extractComponents with the previous state', () => {
    let tmpDir: string;
    let root: string;
    let context: AnalysisContext | undefined;
    let previous: ComponentExtractionState | undefined;
    let first: ComponentExtraction;

    const read = (file: string): string => fs.readFileSync(path.join(root, file), 'utf8');
    const write = (file: string, content: string): void => {
        fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
        fs.writeFileSync(path.join(root, file), content);
    };
    const edit = (file: string, search: string, replacement: string): void => {
        const content = read(file);

        if (!content.includes(search)) {
            throw new Error(`${file} does not contain ${search}`);
        }

        write(file, content.replace(search, replacement));
    };

    const outcome = ({ result, diagnostics }: ComponentExtraction) => ({ result, diagnostics });

    /** Rebuilds like the snapshot service does and checks the outcome against a fresh extraction. */
    const refresh = async (): Promise<ComponentExtraction> => {
        context = createAnalysisContext(root, { previous: context });
        const extraction = await extractComponents(context, { previous });
        previous = extraction.state;

        const fresh = await extractComponents(createAnalysisContext(root));

        expect(fresh.extracted).toHaveLength(fresh.result.length);
        expect(outcome(extraction)).toEqual(outcome(fresh));

        return extraction;
    };

    const component = (extraction: ComponentExtraction, name: string): ComponentEntry => {
        const entry = extraction.result.find((candidate) => candidate.name === name);

        if (!entry) {
            throw new Error(`Component ${name} was not extracted`);
        }

        return entry;
    };

    const returnTypes = (entry: ComponentEntry) =>
        Object.fromEntries(entry.methods.map(({ name, returnType }) => [name, returnType]));

    beforeEach(async () => {
        tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-reuse-')));
        root = path.join(tmpDir, 'admin-root');
        fs.cpSync(FIXTURE_ROOT, root, { recursive: true });
        fs.symlinkSync(ADMIN_NODE_MODULES, path.join(root, 'node_modules'), 'dir');
        context = undefined;
        previous = undefined;
        first = await refresh();
    }, 60_000);

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('reuses every entry when nothing changed', async () => {
        expect(first.extracted).toEqual([
            'sw-reuse-plain',
            'sw-reuse-typed',
        ]);
        expect(returnTypes(component(first, 'sw-reuse-typed'))).toEqual({ unit: '"px"', layout: '"compact"' });

        const second = await refresh();

        expect(second.extracted).toEqual([]);
        expect(outcome(second)).toEqual(outcome(first));
    }, 60_000);

    it('extracts only a changed component and shows its change', async () => {
        edit(`${PLAIN_DIR}/index.js`, '        save() {},\n', '        save() {},\n\n        cancel() {},\n');

        const second = await refresh();

        expect(second.extracted).toEqual(['sw-reuse-plain']);
        expect(component(second, 'sw-reuse-plain').methods.map(({ name }) => name)).toEqual([
            'save',
            'cancel',
        ]);
        expect(component(second, 'sw-reuse-typed')).toEqual(component(first, 'sw-reuse-typed'));
    }, 60_000);

    it('updates the type texts of a component when a module it imports changes', async () => {
        edit('src/core/units.ts', "'px'", "'rem'");

        const second = await refresh();

        expect(second.extracted).toEqual(['sw-reuse-typed']);
        expect(returnTypes(component(second, 'sw-reuse-typed'))).toMatchObject({ unit: '"rem"' });
    }, 60_000);

    it('updates the type texts of a component when a global declaration changes', async () => {
        edit('types/global.d.ts', "FIXTURE_LAYOUT: 'compact'", "FIXTURE_LAYOUT: 'spacious'");

        const second = await refresh();

        expect(second.extracted).toContain('sw-reuse-typed');
        expect(returnTypes(component(second, 'sw-reuse-typed'))).toMatchObject({ layout: '"spacious"' });
    }, 60_000);

    it('updates the blocks of a component when its template changes', async () => {
        edit(
            `${TYPED_DIR}/sw-reuse-typed.html.twig`,
            '{% block sw_reuse_typed_content %}{% endblock %}',
            '{% block sw_reuse_typed_content %}{% endblock %}\n    {% block sw_reuse_typed_footer %}{% endblock %}',
        );

        const second = await refresh();

        expect(second.extracted).toEqual(['sw-reuse-typed']);
        expect(component(second, 'sw-reuse-typed').blocks.map(({ key }) => key)).toEqual([
            'sw_reuse_typed',
            'sw_reuse_typed/sw_reuse_typed_content',
            'sw_reuse_typed/sw_reuse_typed_footer',
        ]);
    }, 60_000);

    it('updates the target of an override when the override or its template changes', async () => {
        expect(component(first, 'sw-reuse-plain').blocks.map(({ name }) => name)).toEqual([
            'sw_reuse_plain',
            'sw_reuse_plain_body',
            'sw_reuse_plain_extra',
        ]);

        edit(`${OVERRIDE_DIR}/sw-reuse-plain.html.twig`, 'sw_reuse_plain_extra', 'sw_reuse_plain_more');
        edit(
            `${OVERRIDE_DIR}/index.js`,
            '    template,\n',
            '    template,\n\n    methods: {\n        reset() {},\n    },\n',
        );

        const second = await refresh();

        expect(second.extracted).toEqual(['sw-reuse-plain']);
        expect(component(second, 'sw-reuse-plain')).toMatchObject({
            overriddenBy: [`${OVERRIDE_DIR}/index.js`],
            methods: [
                expect.objectContaining({ name: 'save' }),
                expect.objectContaining({ name: 'reset' }),
            ],
            blocks: [
                expect.objectContaining({ name: 'sw_reuse_plain' }),
                expect.objectContaining({ name: 'sw_reuse_plain_body' }),
                expect.objectContaining({ name: 'sw_reuse_plain_more' }),
            ],
        });
    }, 60_000);

    it('lists an added component and drops a removed one', async () => {
        write(`${MODULE_DIR}/component/sw-reuse-added/index.js`, 'export default {\n    props: ["label"],\n};\n');
        edit(
            `${MODULE_DIR}/index.js`,
            "Shopware.Component.register('sw-reuse-typed', () => import('./component/sw-reuse-typed'));\n",
            "Shopware.Component.register('sw-reuse-added', () => import('./component/sw-reuse-added'));\n",
        );

        const second = await refresh();

        expect(second.extracted).toEqual(['sw-reuse-added']);
        expect(second.result.map(({ name }) => name)).toEqual([
            'sw-reuse-added',
            'sw-reuse-plain',
        ]);
    }, 60_000);

    it('returns to the first result once a sequence of edits is reverted', async () => {
        const units = read('src/core/units.ts');
        const template = read(`${PLAIN_DIR}/sw-reuse-plain.html.twig`);

        edit('src/core/units.ts', "'px'", "'em'");
        await refresh();
        edit(`${PLAIN_DIR}/sw-reuse-plain.html.twig`, 'sw_reuse_plain_body', 'sw_reuse_plain_main');
        await refresh();
        write('src/core/units.ts', units);
        write(`${PLAIN_DIR}/sw-reuse-plain.html.twig`, template);

        const reverted = await refresh();

        expect(outcome(reverted)).toEqual(outcome(first));
    }, 120_000);

    it('updates the type texts of a component when a JSDoc type of a global script changes', async () => {
        write('src/core/tones.js', "/** @typedef {'soft'} FixtureTone */\n");
        edit(
            `${PLAIN_DIR}/index.js`,
            '        save() {},\n',
            '        save() {},\n\n        /** @returns {FixtureTone} */\n        tone() {},\n',
        );
        await refresh();

        edit('src/core/tones.js', "'soft'", "'loud'");

        const second = await refresh();

        expect(second.extracted).toContain('sw-reuse-plain');
        expect(returnTypes(component(second, 'sw-reuse-plain'))).toMatchObject({ tone: '"loud"' });
    }, 60_000);

    it('follows an import to a file added where an earlier lookup failed', async () => {
        write('lib/variant/index.ts', "export const VARIANT = 'directory' as const;\n");
        edit(
            `${TYPED_DIR}/index.ts`,
            "import template from './sw-reuse-typed.html.twig';\n",
            "import template from './sw-reuse-typed.html.twig';\nimport { VARIANT } from '../../../../../lib/variant';\n",
        );
        edit(
            `${TYPED_DIR}/index.ts`,
            '    methods: {\n',
            '    methods: {\n        variant() {\n            return VARIANT;\n        },\n\n',
        );
        await refresh();

        write('lib/variant.ts', "export const VARIANT = 'file' as const;\n");

        const second = await refresh();

        expect(returnTypes(component(second, 'sw-reuse-typed'))).toMatchObject({ variant: '"file"' });
    }, 60_000);

    it('reports an unreadable template instead of failing or reusing the old blocks', async () => {
        const template = path.join(root, `${PLAIN_DIR}/sw-reuse-plain.html.twig`);
        fs.chmodSync(template, 0o000);

        try {
            const second = await refresh();

            expect(second.extracted).toEqual(['sw-reuse-plain']);
            expect(component(second, 'sw-reuse-plain').blocks.map(({ name }) => name)).not.toContain('sw_reuse_plain');
        } finally {
            fs.chmodSync(template, 0o644);
        }

        const restored = await refresh();

        expect(outcome(restored)).toEqual(outcome(first));
    }, 60_000);
});
