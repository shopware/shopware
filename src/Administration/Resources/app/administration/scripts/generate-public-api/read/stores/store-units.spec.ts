/**
 * @sw-package framework
 *
 * What the snapshot says about a registered store, for every way the sources register and define one.
 */
import type { SourceTree } from '../../source/source-tree';
import { snapshotOf } from '../../snapshot/snapshot';
import { OPTIONS, treeOf } from '../../testing/example-components';
import { optionsTree, STORE_FILE, STORE_REPORT, storeFile, storeTree } from '../../testing/example-stores';

const COMPOSABLE = 'src/app/composables/use-example.ts';

const COMPOSABLE_REPORT = 'app/composables/use-example.api.md';

function files(tree: SourceTree): Record<string, string> {
    return snapshotOf(tree, OPTIONS).files;
}

/** The lines of the outlines in a report, without the empty ones. */
function outline(tree: SourceTree, report = STORE_REPORT): string[] {
    const [, block = ''] = /```text\n([\s\S]*?)\n```/.exec(files(tree)[report] ?? '') ?? [];

    return block.split('\n').filter((line) => line !== '');
}

/** The lines of the sections of a report, which follow the three header lines of its one store. */
function sections(tree: SourceTree, report = STORE_REPORT): string[] {
    return outline(tree, report).slice(3);
}

/** A setup store whose function stands in a file of its own, as a composable. */
function composableTree(composable: string): SourceTree {
    return storeTree(
        "'example', useExample",
        { [COMPOSABLE]: composable },
        "import useExample from '../composables/use-example';",
    );
}

describe('scripts/generate-public-api/read/stores/store-units', () => {
    describe('an options store', () => {
        it('lists the state, the getters and the actions with the types the source writes', () => {
            const tree = optionsTree(
                [
                    'state: (): ExampleState => ({ items: [], limit: 25, filter: {} as Filter, total: 0 }),',
                    'getters: { hasItems(): boolean { return true; }, first: (state) => state.items[0] },',
                    'actions: { addItem(item: string, position = 0): void {}, async load(id) {} },',
                ].join('\n'),
                'interface ExampleState { items: string[]; limit: number; filter: unknown }',
            );

            expect(outline(tree)).toEqual([
                'store example  public  ts  untyped  options',
                '  registered src/app/store/example.store.ts',
                '  source src/app/store/example.store.ts',
                'state',
                '  filter: Filter',
                '  items: string[]',
                '  limit: number',
                '  total: any',
                'getters',
                '  first: any',
                '  hasItems: boolean',
                'actions',
                '  addItem(item: string, position?: any = 0): void',
                '  load(id: any): Promise<any>',
            ]);
        });

        it.each([
            ['a type literal the state function returns', 'state: (): { items: string[] } => ({ items: [] })'],
            ['a type alias of the same file', 'state: (): ExampleState => ({ items: [] })'],
            ['a type the returned object is asserted to be', 'state: () => ({ items: [] }) as ExampleState'],
            ['a function with a block body', 'state() { return { items: [] as string[] }; }'],
        ])('reads the type of a state property from %s', (_, state) => {
            expect(sections(optionsTree(state, 'type ExampleState = { items: string[] };'))).toEqual([
                'state',
                '  items: string[]',
            ]);
        });

        it.each([
            ['the id as the first argument', "'example', { state: () => ({ items: [] }) }", ''],
            [
                'a definition that a constant holds',
                'definition',
                "const definition = { id: 'example', state: () => ({ items: [] }) };",
            ],
        ])('reads a registration with %s', (_, registerArguments, before) => {
            expect(sections(storeTree(registerArguments, {}, before))).toEqual(['state', '  items: any']);
        });

        it('reads a registration through Store.instance', () => {
            const tree = treeOf({
                [STORE_FILE]: "Store.instance.register({ id: 'example', actions: { load() {} } });",
            });

            expect(sections(tree)).toEqual(['actions', '  load(): any']);
        });

        it('follows a getter and an action to the file that declares them, with their markers', () => {
            const tree = storeTree(
                "{ id: 'example', getters: { total }, actions: { load } }",
                {
                    'src/app/store/example.actions.ts': [
                        'export function total(): number { return 1; }',
                        '/** @deprecated tag:v6.8.0 - Use find instead. */',
                        'export function load(id: string): Promise<void> {}',
                    ].join('\n'),
                },
                "import { load, total } from './example.actions';",
            );

            expect(sections(tree)).toEqual([
                'getters',
                '  total: number',
                'actions',
                '  load(id: string): Promise<void>  [deprecated tag:v6.8.0]',
            ]);
        });

        it('notes what it cannot read of an option', () => {
            const tree = optionsTree(
                'state: createState, getters: { ...sharedGetters, total() {} }, actions: { [name]() {} }',
            );

            expect(sections(tree)).toEqual([
                'getters',
                '  total: any',
                'notes',
                '  unresolved actions [name]() {}',
                '  unresolved getters ...sharedGetters',
                '  unresolved state createState',
            ]);
        });
    });

    describe('a setup store', () => {
        it('sorts what the function returns into state, getters and actions, in the file of the function', () => {
            const tree = composableTree(
                [
                    "import { computed, reactive, ref } from 'vue';",
                    'const items = ref<string[]>([]);',
                    'const filter = reactive({ term: "" });',
                    '/** @deprecated tag:v6.8.0 - Use hasItems instead. */',
                    'const isEmpty = computed(() => items.value.length === 0);',
                    'const total = computed((): number => items.value.length);',
                    'const first = computed<string | undefined>(() => items.value[0]);',
                    'function addItem(item: string): void {}',
                    'export default function useExample() {',
                    '    const reset = async () => {};',
                    '    return { items, filter, isEmpty, total, first, addItem, reset, limit: 25, inline: ref(0), find: (id: string) => id };',
                    '}',
                ].join('\n'),
            );

            expect(outline(tree, COMPOSABLE_REPORT)).toEqual([
                'store example  public  ts  untyped  setup',
                '  registered src/app/store/example.store.ts',
                '  source src/app/composables/use-example.ts',
                'state',
                '  filter: any',
                '  inline: any',
                '  items: string[]',
                '  limit: any',
                'getters',
                '  first: string | undefined',
                '  isEmpty: any  [deprecated tag:v6.8.0]',
                '  total: number',
                'actions',
                '  addItem(item: string): void',
                '  find(id: string): any',
                '  reset(): Promise<any>',
            ]);
        });

        it('reads a function that is written into the registration', () => {
            const tree = storeTree("'example', () => { const items = ref([]); return { items, clear() {} }; }");

            expect(sections(tree)).toEqual([
                'state',
                '  items: any',
                'actions',
                '  clear(): any',
            ]);
        });

        it.each([
            ['directly', '...state'],
            ['as refs', '...toRefs(state)'],
        ])('lists the keys of a reactive object that the function spreads %s', (_, spread) => {
            const tree = composableTree(
                `const state = reactive({ api: {}, app: {} });\nexport default function useExample() { return { ${spread}, load() {} }; }`,
            );

            expect(sections(tree, COMPOSABLE_REPORT)).toEqual([
                'state',
                '  api: any',
                '  app: any',
                'actions',
                '  load(): any',
            ]);
        });

        it('lists the keys of an object literal of another file that the function spreads, as state', () => {
            const tree = storeTree(
                "'example', useExample",
                {
                    [COMPOSABLE]: [
                        "import { shared } from './shared';",
                        'export default function useExample() { return { ...shared, load() {} }; }',
                    ].join('\n'),
                    'src/app/composables/shared.ts': 'export const shared = { limit: 25, [key]: 1 };',
                },
                "import useExample from '../composables/use-example';",
            );

            expect(sections(tree, COMPOSABLE_REPORT)).toEqual([
                'state',
                '  limit: any',
                'actions',
                '  load(): any',
                'notes',
                '  unresolved members [key]: 1',
            ]);
        });

        it('notes what the returned object holds besides the members it names', () => {
            const tree = composableTree(
                'export default function useExample() { return { ...useOther(), [key]: 1, load() {} }; }',
            );

            expect(sections(tree, COMPOSABLE_REPORT)).toEqual([
                'actions',
                '  load(): any',
                'notes',
                '  unresolved members ...useOther()',
                '  unresolved members [key]: 1',
            ]);
        });

        it('notes a function that returns no object literal, and a definition it cannot follow', () => {
            const built = composableTree('export default function useExample() { return buildStore(); }');
            const imported = storeTree("'example', usePackageStore", {}, "import usePackageStore from 'some-package';");

            expect(sections(built, COMPOSABLE_REPORT)).toEqual([
                'notes',
                '  unresolved store export default function useExample() { return buildStore(); }',
            ]);
            expect(outline(imported)).toEqual([
                'store example  public  ts  untyped  unknown',
                '  registered src/app/store/example.store.ts',
                '  source src/app/store/example.store.ts',
                'notes',
                '  unresolved store usePackageStore',
            ]);
        });
    });

    describe('registrations', () => {
        it.each([
            [
                'a registration without a literal id',
                { [STORE_FILE]: 'Shopware.Store.register({ id: name, state: () => ({}) });' },
                'src/app/store/example.store.ts:1: a store needs a literal id and a definition',
            ],
            [
                'an id that is registered twice',
                { [STORE_FILE]: storeFile("'example', {}"), 'src/app/store/other.store.ts': storeFile("'example', {}") },
                'The store example is registered twice, in src/app/store/example.store.ts and in src/app/store/other.store.ts.',
            ],
        ])('rejects %s', (_, sources, message) => {
            expect(() => files(treeOf(sources))).toThrow(message);
        });

        it('leaves out the file that implements register, which passes on the id it gets', () => {
            const tree = treeOf({
                'src/app/store/index.ts':
                    'export default class Store { register = (id, definition) => Store.instance.register(id, definition); }',
            });

            expect(JSON.parse(files(tree)['index.json'])).toMatchObject({ stores: {} });
        });

        it('marks a store that is registered in one flag state only, with its members', () => {
            const tree = treeOf({
                [STORE_FILE]:
                    "if (Shopware.Feature.isActive('v6.8.0.0')) {\n    Shopware.Store.register({ id: 'example', actions: { load() {} } });\n}",
            });

            expect(outline(tree)).toEqual([
                'store example  public  ts  untyped  options  [from v6.8.0]',
                '  registered src/app/store/example.store.ts',
                '  source src/app/store/example.store.ts',
                'actions',
                '  load(): any  [from v6.8.0]',
            ]);
        });
    });

    describe('visibility and markers', () => {
        it.each([
            [
                'the constant that holds it',
                "/** @private */\nconst exampleStore = Shopware.Store.register({ id: 'example' });",
            ],
            [
                'its default export',
                "const exampleStore = Shopware.Store.register({ id: 'example' });\n\n/**\n * @private\n */\nexport default exampleStore;",
            ],
        ])('marks a store with a private tag above %s as private', (_, store) => {
            expect(outline(treeOf({ [STORE_FILE]: store }))).toEqual([
                'store example  private  ts  untyped  options  [private]',
                '  registered src/app/store/example.store.ts  [private]',
                '  source src/app/store/example.store.ts  [private]',
                '  visibility-source export  [private]',
            ]);
        });

        it('ignores a private tag on the type of the store, and reads the markers of the store and of a member', () => {
            const store = [
                '/** @deprecated tag:v6.8.0 - Will be removed. */',
                "const exampleStore = Shopware.Store.register({ id: 'example', actions: {",
                '    /** @private */',
                '    refresh() {},',
                '} });',
                '',
                '/**',
                ' * @private',
                ' */',
                'export type ExampleStore = ReturnType<typeof exampleStore>;',
            ].join('\n');

            expect(outline(treeOf({ [STORE_FILE]: store }))).toEqual([
                'store example  public  ts  untyped  options  [deprecated tag:v6.8.0]',
                '  registered src/app/store/example.store.ts',
                '  source src/app/store/example.store.ts',
                'actions',
                '  refresh(): any  [private]',
            ]);
        });
    });

    describe('the files of a store', () => {
        it('writes a report beside the source of the store and an index entry', () => {
            const generated = files(optionsTree('state: () => ({ items: [] })'));

            expect(Object.keys(generated).sort()).toEqual([STORE_REPORT, 'index.json']);
            expect(generated[STORE_REPORT].split('\n').slice(0, 5)).toEqual([
                '# Stores of src/app/store/example.store.ts',
                '',
                '<!-- Generated by `composer admin:generate-public-api`. Do not edit. -->',
                '',
                'API report of the stores of `src/app/store/example.store.ts` in both flag states.',
            ]);
            expect(JSON.parse(generated['index.json'])).toMatchObject({
                stores: { example: { file: 'app/store/example.store', visibility: 'public' } },
            });
        });

        it('says whether PiniaRootState has an entry for the id', () => {
            const rootState = (entry: string): SourceTree =>
                storeTree("{ id: 'example' }", {
                    'src/global.types.ts': `declare global {\n    interface PiniaRootState {\n        ${entry}\n    }\n}`,
                });

            expect(outline(rootState('example: ExampleStore;'))[0]).toBe('store example  public  ts  typed  options');
            expect(outline(rootState('other: OtherStore;'))[0]).toBe('store example  public  ts  untyped  options');
        });
    });
});
