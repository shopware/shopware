/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { createAnalysisContext, type AnalysisContext } from '../analysis-context';
import type { ComponentEntry, ExtractionResult } from '../types';
import { extractComponents } from '.';

const FIXTURE_ROOT = path.join(__dirname, '__fixtures__', 'admin-root');
const ADMIN_NODE_MODULES = path.resolve(__dirname, '../../../../node_modules');
const DETAIL_FILE = 'src/module/sw-fixture/page/sw-fixture-detail/index.js';
const DETAIL_TEMPLATE = 'src/module/sw-fixture/page/sw-fixture-detail/sw-fixture-detail.html.twig';
const OVERRIDE_FILE = 'src/module/sw-fixture/extension/sw-fixture-detail/index.js';
const OVERRIDE_TEMPLATE = 'src/module/sw-fixture/extension/sw-fixture-detail/sw-fixture-detail.html.twig';
const TYPED_FILE = 'src/module/sw-fixture/component/sw-fixture-typed/index.ts';

const at = (file: string, line: number) => ({ file, line });
const publicMeta = (file: string, line: number) => ({ visibility: 'public', deprecation: null, location: at(file, line) });

describe('extractComponents', () => {
    let context: AnalysisContext;
    let extracted: ExtractionResult<ComponentEntry[]>;

    const component = (name: string): ComponentEntry => {
        const entry = extracted.result.find((candidate) => candidate.name === name);

        if (!entry) {
            throw new Error(`Component ${name} was not extracted`);
        }

        return entry;
    };

    beforeAll(async () => {
        context = createAnalysisContext(FIXTURE_ROOT);
        extracted = await extractComponents(context);
    }, 60_000);

    it('lists one entry per registered name, sorted by name', () => {
        expect(extracted.result.map(({ name }) => name)).toEqual([
            'sw-fixture-detail',
            'sw-fixture-extended',
            'sw-fixture-private',
            'sw-fixture-typed',
        ]);
    });

    it('reports registrations it cannot extract and overrides without a target', () => {
        expect(extracted.diagnostics).toEqual([
            {
                file: 'src/module/sw-fixture/index.js',
                message:
                    "Component.register('sw-fixture-sfc'): single file components are not supported yet, skipped './component/sw-fixture-sfc.vue'.",
            },
            {
                file: 'src/module/sw-fixture/index.js',
                message: "Component.register('sw-fixture-missing'): cannot resolve './component/sw-fixture-missing'.",
            },
            {
                file: 'src/module/sw-fixture/component/sw-fixture-private/index.js',
                message: 'data() does not return an object literal (line 18)',
            },
            {
                file: OVERRIDE_TEMPLATE,
                message:
                    'The override of sw-fixture-detail declares block sw_fixture_detail_removed (line 9), which sw-fixture-detail does not define; it is ignored.',
            },
            {
                file: 'src/module/sw-fixture/index.js',
                message: "Component.override('sw-fixture-unknown') targets a component that is not registered.",
            },
        ]);
    });

    it('reads the component level metadata of a JS component', () => {
        expect(component('sw-fixture-detail')).toMatchObject({
            name: 'sw-fixture-detail',
            registration: 'register',
            overriddenBy: [OVERRIDE_FILE],
            extends: null,
            packageName: 'inventory',
            language: 'js',
            sourceFile: DETAIL_FILE,
            templateFile: DETAIL_TEMPLATE,
            ...publicMeta(DETAIL_FILE, 14),
        });
    });

    it('extracts props with their declared type, default and deprecation', () => {
        expect(component('sw-fixture-detail').props).toEqual([
            { name: 'productId', type: 'String', required: true, default: null, ...publicMeta(DETAIL_FILE, 24) },
            {
                name: 'value',
                type: 'String | Number',
                required: false,
                default: 'null',
                ...publicMeta(DETAIL_FILE, 28),
            },
            {
                name: 'legacyId',
                type: 'String',
                required: false,
                default: 'null',
                visibility: 'public',
                deprecation: { version: 'v6.8.0', message: 'Use `productId` instead' },
                location: at(DETAIL_FILE, 36),
            },
            {
                name: 'mode',
                type: 'String',
                required: false,
                default: "'edit'",
                visibility: 'public',
                deprecation: { version: 'v6.9.0.0', message: 'Use "variant" instead.' },
                location: at(DETAIL_FILE, 40),
            },
            {
                name: 'items',
                type: 'Array',
                required: false,
                default: 'default() { return []; }',
                ...publicMeta(DETAIL_FILE, 45),
            },
        ]);
    });

    it('collects emits from the option, $emit calls in the script and the template', () => {
        expect(component('sw-fixture-detail').emits).toEqual([
            { name: 'saved', ...publicMeta(DETAIL_FILE, 19) },
            { name: 'save-start', ...publicMeta(DETAIL_FILE, 81) },
            { name: 'cancel', ...publicMeta(DETAIL_TEMPLATE, 24) },
        ]);
    });

    it('lists static slots of the template, the unnamed one as default', () => {
        expect(component('sw-fixture-detail').slots).toEqual([
            { name: 'header', ...publicMeta(DETAIL_TEMPLATE, 6) },
            { name: 'default', ...publicMeta(DETAIL_TEMPLATE, 20) },
            { name: 'footer', ...publicMeta(DETAIL_TEMPLATE, 22) },
        ]);
    });

    it('widens the inferred data types', () => {
        expect(component('sw-fixture-detail').data).toEqual([
            { name: 'isLoading', type: 'boolean', typeSource: 'inferred', ...publicMeta(DETAIL_FILE, 55) },
            { name: 'title', type: 'string', typeSource: 'inferred', ...publicMeta(DETAIL_FILE, 56) },
            { name: 'counter', type: 'number', typeSource: 'inferred', ...publicMeta(DETAIL_FILE, 57) },
            { name: 'selection', type: 'null', typeSource: 'inferred', ...publicMeta(DETAIL_FILE, 58) },
        ]);
    });

    it('expands computed spreads and marks computed setters as writable', () => {
        expect(component('sw-fixture-detail').computed).toEqual([
            {
                name: 'productNameError',
                type: 'boolean',
                typeSource: 'inferred',
                writable: false,
                ...publicMeta(DETAIL_FILE, 63),
            },
            { name: 'heading', type: 'string', typeSource: 'inferred', writable: false, ...publicMeta(DETAIL_FILE, 65) },
            { name: 'searchTerm', type: 'any', typeSource: 'inferred', writable: true, ...publicMeta(DETAIL_FILE, 69) },
        ]);
    });

    it('extracts method signatures, keeps the own declaration over an override and adds what the override adds', () => {
        expect(component('sw-fixture-detail').methods).toEqual([
            {
                name: 'onSave',
                parameters: [
                    { name: 'product', type: 'any', optional: false, rest: false },
                    { name: 'rest', type: 'any[]', optional: false, rest: true },
                ],
                returnType: 'Promise<boolean>',
                async: true,
                typeSource: 'inferred',
                ...publicMeta(DETAIL_FILE, 80),
            },
            {
                name: 'resetForm',
                parameters: [{ name: 'force', type: 'boolean', optional: true, rest: false }],
                returnType: 'void',
                async: false,
                typeSource: 'inferred',
                visibility: 'private',
                deprecation: null,
                location: at(DETAIL_FILE, 91),
            },
            {
                name: '_cleanUp',
                parameters: [],
                returnType: 'void',
                async: false,
                typeSource: 'inferred',
                visibility: 'private',
                deprecation: null,
                location: at(DETAIL_FILE, 95),
            },
            {
                name: 'countLetters',
                parameters: [{ name: 'name', type: 'string', optional: false, rest: false }],
                returnType: 'number',
                async: false,
                typeSource: 'jsdoc',
                ...publicMeta(DETAIL_FILE, 103),
            },
            {
                name: 'onExtensionSave',
                parameters: [],
                returnType: 'boolean',
                async: false,
                typeSource: 'inferred',
                ...publicMeta(OVERRIDE_FILE, 13),
            },
        ]);
    });

    it('merges inject entries of overrides and resolves mixins by name', () => {
        const { inject, mixins } = component('sw-fixture-detail');

        expect(inject.map(({ name }) => name)).toEqual([
            'repositoryFactory',
            'acl',
            'systemConfigApiService',
        ]);
        expect(mixins.map(({ name }) => name)).toEqual([
            'notification',
            'placeholder',
        ]);
    });

    it('builds the block tree with keys for duplicate names, deprecations and blocks nested into target blocks by overrides', () => {
        const blocks = component('sw-fixture-detail').blocks.map(
            ({ name, key, kind, parent, depth, visibility, deprecation, location }) => ({
                name,
                key,
                kind,
                parent,
                depth,
                visibility,
                deprecation,
                location,
            }),
        );

        expect(blocks).toEqual([
            {
                name: 'sw_fixture_detail',
                key: 'sw_fixture_detail',
                kind: 'twig',
                parent: null,
                depth: 0,
                visibility: 'public',
                deprecation: { version: 'v6.8.0', message: 'The wrapper block will be removed' },
                location: at(DETAIL_TEMPLATE, 2),
            },
            {
                name: 'sw_fixture_detail_header',
                key: 'sw_fixture_detail/sw_fixture_detail_header',
                kind: 'twig',
                parent: 'sw_fixture_detail',
                depth: 1,
                ...publicMeta(DETAIL_TEMPLATE, 4),
            },
            {
                name: 'sw_fixture_detail_content',
                key: 'sw_fixture_detail/sw_fixture_detail_content',
                kind: 'twig',
                parent: 'sw_fixture_detail',
                depth: 1,
                ...publicMeta(DETAIL_TEMPLATE, 12),
            },
            {
                name: 'sw_fixture_detail_content',
                key: 'sw_fixture_detail/sw_fixture_detail_content~2',
                kind: 'twig',
                parent: 'sw_fixture_detail',
                depth: 1,
                visibility: 'public',
                deprecation: { version: 'v6.9.0', message: 'Use the header slot instead' },
                location: at(DETAIL_TEMPLATE, 17),
            },
            {
                name: 'sw_fixture_detail_header_extension',
                key: 'sw_fixture_detail/sw_fixture_detail_header/sw_fixture_detail_header_extension',
                kind: 'twig',
                parent: 'sw_fixture_detail_header',
                depth: 2,
                ...publicMeta(OVERRIDE_TEMPLATE, 4),
            },
        ]);
    });

    it('keeps only the own markup of a block and replaces nested blocks with placeholders', () => {
        const [root] = component('sw-fixture-detail').blocks;

        expect(root.content).toBe(
            [
                '<div class="sw-fixture-detail">',
                '    {% block sw_fixture_detail_header %}{% endblock %}',
                '',
                '    {# {% block sw_fixture_detail_commented %}{% endblock %} #}',
                '',
                '    <template v-if="isLoading">',
                '        {% block sw_fixture_detail_content %}{% endblock %}',
                '    </template>',
                '    <template v-else>',
                '        {% block sw_fixture_detail_content %}{% endblock %}',
                '    </template>',
                '',
                '    {% raw %}{% block sw_fixture_detail_raw %}{% endblock %}{% endraw %}',
                '</div>',
            ].join('\n'),
        );
    });

    it('uses the instance type of a wrapComponentConfig component', () => {
        const { props, emits, data, computed, methods, blocks, ...meta } = component('sw-fixture-typed');

        expect(meta).toMatchObject({
            language: 'ts',
            packageName: 'framework',
            visibility: 'experimental',
            templateFile: 'src/module/sw-fixture/component/shared/sw-fixture-typed.twig',
        });
        expect(props.map(({ name, type }) => [name, type])).toEqual([
            ['modelValue', 'String'],
            ['size', '"large" | "small"'],
        ]);
        expect(emits.map(({ name }) => name)).toEqual(['update:modelValue']);
        expect(data).toEqual([
            { name: 'selected', type: 'null | string', typeSource: 'typescript', ...publicMeta(TYPED_FILE, 43) },
            { name: 'open', type: 'boolean', typeSource: 'typescript', ...publicMeta(TYPED_FILE, 44) },
            {
                name: 'layout',
                type: '{ align: "end" | "start"; }',
                typeSource: 'typescript',
                ...publicMeta(TYPED_FILE, 45),
            },
            {
                name: 'settings',
                type: expect.stringMatching(
                    /^\{ eighthOptionWithAVeryLongName: string; .{200,}… \[\+\d+ chars #[0-9a-f]{8}\]$/,
                ) as string,
                typeSource: 'typescript',
                ...publicMeta(TYPED_FILE, 46),
            },
        ]);
        expect(computed.map(({ name, type, writable }) => [name, type, writable])).toEqual([
            ['label', 'string', false],
            ['current', 'null | string', true],
        ]);
        expect(methods).toEqual([
            {
                name: 'select',
                parameters: [
                    { name: 'value', type: 'string', optional: false, rest: false },
                    { name: 'silent', type: 'boolean | undefined', optional: true, rest: false },
                ],
                returnType: 'void',
                async: false,
                typeSource: 'typescript',
                ...publicMeta(TYPED_FILE, 76),
            },
        ]);
        expect(blocks.map(({ key }) => key)).toEqual(['sw_fixture_typed']);
    });

    it('names the parent of an extend component and lists no blocks without an own template', () => {
        expect(component('sw-fixture-extended')).toMatchObject({
            registration: 'extend',
            extends: 'sw-fixture-detail',
            templateFile: null,
            blocks: [],
            deprecation: { version: 'v6.9.0.0', message: 'Use "sw-fixture-detail" instead.' },
            methods: [expect.objectContaining({ name: 'onSave', returnType: 'boolean' })],
        });
    });

    it('reads private components and blocks of an inline template', () => {
        const file = 'src/module/sw-fixture/component/sw-fixture-private/index.js';

        expect(component('sw-fixture-private')).toMatchObject({
            visibility: 'private',
            templateFile: file,
            props: [
                { name: 'label', type: null },
                { name: 'value', type: null },
            ],
            data: [],
            slots: [{ name: 'content', location: at(file, 11) }],
            blocks: [
                { name: 'sw_fixture_private', content: '<div><slot name="content"></slot></div>', location: at(file, 10) },
            ],
        });
    });

    it('prints identical output for the same sources in another root', async () => {
        const tmpDir = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-components-')));

        try {
            const copy = path.join(tmpDir, 'admin-root');
            fs.cpSync(FIXTURE_ROOT, copy, { recursive: true });
            fs.symlinkSync(ADMIN_NODE_MODULES, path.join(copy, 'node_modules'), 'dir');

            const outcome = ({ result, diagnostics }: ExtractionResult<ComponentEntry[]>) =>
                JSON.stringify({ result, diagnostics });
            const again = await extractComponents(context);
            const elsewhere = await extractComponents(createAnalysisContext(copy));

            expect(outcome(again)).toBe(outcome(extracted));
            expect(outcome(elsewhere)).toBe(outcome(extracted));
        } finally {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        }
    }, 60_000);
});
