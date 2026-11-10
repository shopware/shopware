const fs = require('fs');
const os = require('os');
const path = require('path');
const tsParser = require('@typescript-eslint/parser');
const vueParser = require('vue-eslint-parser');
const {
    componentOptions,
    componentsAt,
    definesMember,
    fileComponents,
    fileOwnMembers,
    isService,
    isStore,
    siblingIndexDefines,
} = require('./component-scope');

/**
 * Parses TypeScript and returns the first node `predicate` accepts, with parents set.
 */
function findNode(code, predicate) {
    const { ast, visitorKeys } = tsParser.parseForESLint(code, { range: true, loc: true });
    let found = null;

    (function visit(node, parent) {
        node.parent = parent;

        if (!found && predicate(node)) {
            found = node;
        }

        (visitorKeys[node.type] ?? []).forEach((key) => {
            [node[key]]
                .flat()
                .filter((child) => child && typeof child.type === 'string')
                .forEach((child) => visit(child, node));
        });
    })(ast, null);

    return found;
}

function thisMember(code, name) {
    return findNode(code, (node) => {
        return node.type === 'MemberExpression' && node.object.type === 'ThisExpression' && node.property.name === name;
    });
}

function receiverOf(code, property) {
    return findNode(code, (node) => node.type === 'MemberExpression' && node.property.name === property).object;
}

describe('registry/component-scope', () => {
    let root;

    function write(relativePath, content = '') {
        const filename = path.join(root, relativePath);

        fs.mkdirSync(path.dirname(filename), { recursive: true });
        fs.writeFileSync(filename, content);

        return filename;
    }

    beforeAll(() => {
        root = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-component-scope-'));
    });

    afterAll(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    describe('fileComponents', () => {
        it('maps native override files to their component', () => {
            expect(fileComponents('/ext/src/sw-order-list.override.vue')).toEqual(['sw-order-list']);
            expect(fileComponents('/ext/src/sw-order-list/index.override.vue')).toEqual(['sw-order-list']);
        });

        it('maps base components and scripts to no component', () => {
            expect(fileComponents('/ext/src/sw-order-list/sw-order-list.vue')).toEqual([]);
            expect(fileComponents('/ext/src/sw-order-list/index.js')).toEqual([]);
            expect(fileComponents('/ext/src/sw-order-list/store.ts')).toEqual([]);
        });

        it('maps a template to what its sibling index overrides, also under another name', () => {
            write('override/index.js', "import template from './swag.html.twig';\nShopware.Component.override('sw-order-list', { template });");

            expect(fileComponents(write('override/swag.html.twig'))).toEqual(['sw-order-list']);
        });

        it('maps a template to the component its sibling index extends', () => {
            write('extend/index.ts', "Component.extend('swag-order-list', 'sw-order-list', { template });");

            expect(fileComponents(write('extend/swag-order-list.html.twig'))).toEqual(['sw-order-list']);
        });

        it('maps a template whose sibling index registers a component of its own to no component', () => {
            write('own/sw-order-list/index.js', "Shopware.Component.register('swag-order-list', { template });");

            expect(fileComponents(write('own/sw-order-list/sw-order-list.html.twig'))).toEqual([]);
        });

        it.each([
            ['export-default', 'export default { template };'],
            ['wrap-component-config', 'export default Shopware.Component.wrapComponentConfig({ template });'],
        ])('maps the template of a lazy override, whose index only exports options (%s), by its folder and base name', (folder, code) => {
            write(`lazy-${folder}/sw-order-list/index.ts`, code);

            expect(fileComponents(write(`lazy-${folder}/sw-order-list/swag-order-list.html.twig`))).toEqual([
                'sw-order-list',
                'swag-order-list',
            ]);
        });

        it('maps the files of the Administration itself to no component', () => {
            const administration = path.resolve(__dirname, '../../../src');

            // The sibling index of this core template exports options, which in an extension marks a lazy override.
            expect(fileComponents(path.join(administration, 'module/sw-order/page/sw-order-list/sw-order-list.html.twig'))).toEqual([]);
            // The sibling index of this core template overrides sw-admin-menu.
            expect(fileComponents(path.join(administration, 'module/sw-profile/extension/sw-admin-menu/sw-admin-menu.html.twig'))).toEqual([]);
            expect(fileComponents(path.join(administration, 'app/adapter/_mocks_/sw-jest-transform-fixture.override.vue'))).toEqual([]);
        });

        it('maps a template without sibling index to its folder and base name', () => {
            expect(fileComponents(write('loose/sw-order-list/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
            expect(fileComponents(write('loose/sw-order-list/sw-order-list-grid.html.twig'))).toEqual([
                'sw-order-list',
                'sw-order-list-grid',
            ]);
        });

        it('maps a template whose sibling index says nothing about a component by its folder and base name', () => {
            write('imports/sw-order-list/index.js', "import './sw-order-list.scss';");

            expect(fileComponents(write('imports/sw-order-list/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
        });
    });

    describe('componentsAt', () => {
        const context = { physicalFilename: '/ext/src/sw-order-detail.override.vue' };

        it('takes the component of the enclosing override or extension', () => {
            expect(
                componentsAt(context, thisMember("Shopware.Component.override('sw-order-list', { a() { this.x; } });", 'x')),
            ).toEqual(['sw-order-list']);
            expect(componentsAt(context, thisMember("Component.extend('swag', 'sw-order-list', { a() { this.x; } });", 'x'))).toEqual([
                'sw-order-list',
            ]);
        });

        it('counts the registration of a component as no override, whatever the file name', () => {
            expect(componentsAt(context, thisMember("Shopware.Component.register('sw-order-list', { a() { this.x; } });", 'x'))).toEqual(
                [],
            );
        });

        it('counts no spot of the Administration itself as override', () => {
            const coreContext = { physicalFilename: path.resolve(__dirname, '../../../src/module/sw-profile/extension/sw-admin-menu/index.js') };

            expect(
                componentsAt(coreContext, thisMember("Shopware.Component.override('sw-admin-menu', { a() { this.x; } });", 'x')),
            ).toEqual([]);
            expect(componentsAt(coreContext, null)).toEqual([]);
        });

        it('falls back to the components of the file', () => {
            expect(componentsAt(context, thisMember('export default { a() { this.x; } };', 'x'))).toEqual(['sw-order-detail']);
            expect(componentsAt(context, null)).toEqual(['sw-order-detail']);
            expect(componentsAt({ filename: '/ext/src/sw-order-detail/store.js' }, null)).toEqual([]);
        });

        it('maps the default export of a lazy override index to its folder', () => {
            const indexFile = write('lazy/sw-order-list/index.js', 'export default { a() { this.x; } };');
            const node = thisMember('export default { a() { this.x; } };', 'x');

            expect(componentsAt({ physicalFilename: indexFile }, node)).toEqual(['sw-order-list']);
            expect(componentsAt({ physicalFilename: path.join(root, 'lazy/sw-order-list/store.js') }, node)).toEqual([]);
            expect(componentsAt({ physicalFilename: indexFile }, thisMember('const a = { b() { this.x; } };', 'x'))).toEqual([]);
        });

        it('maps the default export of an index that registers its own component to no component', () => {
            const code = "Shopware.Component.register('swag-list', () => import('./a'));\nexport default { a() { this.x; } };";
            const indexFile = write('own/swag-list/index.js', code);

            expect(componentsAt({ physicalFilename: indexFile }, thisMember(code, 'x'))).toEqual([]);
        });
    });

    describe('componentOptions and definesMember', () => {
        it('finds options wrapped in as and satisfies', () => {
            const node = thisMember("Shopware.Component.override('sw-x', { a() { this.x; } } as unknown as Options);", 'x');
            const options = componentOptions(node);

            expect(options.type).toBe('ObjectExpression');
            expect(options.properties[0].key.name).toBe('a');
            expect(componentOptions(thisMember('export default { a() { this.x; } } satisfies Options;', 'x'))).not.toBeNull();
        });

        it('ignores objects of other calls', () => {
            expect(componentOptions(thisMember('store.save({ a() { this.x; } });', 'x'))).toBeNull();
        });

        it('finds members of every kind of section', () => {
            const options = componentOptions(
                thisMember(
                    "export default { props: ['a'], inject: { b: 'key' }, data: () => ({ c: 1 } as const), computed: { d() { this.x; } } };",
                    'x',
                ),
            );
            const sections = ['props', 'inject', 'data', 'computed'];

            expect(['a', 'b', 'c', 'd'].map((name) => definesMember(options, sections, name))).toEqual([true, true, true, true]);
            expect(definesMember(options, ['computed'], 'a')).toBe(false);
            expect(definesMember(null, sections, 'a')).toBe(false);
        });
    });

    describe('isService and isStore', () => {
        it.each([
            'this.mailService.send()',
            'mailService.send()',
            "Shopware.Service('mailService').send()",
            "Service('mailService').send()",
            "Shopware.Service().get('mailService').send()",
            '(this.mailService as MailService).send()',
        ])('finds the service in %s', (code) => {
            expect(isService(receiverOf(code, 'send'), 'mailService')).toBe(true);
        });

        it('does not take another service', () => {
            expect(isService(receiverOf("Shopware.Service('other').send()", 'send'), 'mailService')).toBe(false);
            expect(isService(receiverOf("Shopware.Service('a').get('mailService').send()", 'send'), 'mailService')).toBe(false);
        });

        it('finds a Pinia store by its id', () => {
            expect(isStore(receiverOf("Shopware.Store.get('swOrder').loading", 'loading'), 'swOrder')).toBe(true);
            expect(isStore(receiverOf("Store.get('swOrder').loading", 'loading'), 'swOrder')).toBe(true);
            expect(isStore(receiverOf("Shopware.Store.get('other').loading", 'loading'), 'swOrder')).toBe(false);
        });
    });

    describe('siblingIndexDefines', () => {
        it('finds methods, computeds, data keys and listed props of the index next to a template', () => {
            write(
                'defines/index.js',
                [
                    "Shopware.Component.override('sw-order-list', {",
                    "    inject: ['acl'],",
                    "    props: ['isScoped'],",
                    '    data() { return { total: 0 }; },',
                    '    computed: { dateFilter(): Filter { return this.$super("dateFilter"); } },',
                    '    methods: { getDeliveryState(order) { return this.helper.isLoading; } },',
                    '});',
                ].join('\n'),
            );
            const template = write('defines/sw-order-list.html.twig');

            expect(['acl', 'isScoped', 'total', 'dateFilter', 'getDeliveryState'].filter((name) => siblingIndexDefines(template, name))).toEqual([
                'acl',
                'isScoped',
                'total',
                'dateFilter',
                'getDeliveryState',
            ]);
            expect(siblingIndexDefines(template, 'isLoading')).toBe(false);
        });

        it('finds nothing without a sibling index', () => {
            expect(siblingIndexDefines(write('no-index/sw-order-list.html.twig'), 'dateFilter')).toBe(false);
        });
    });

    describe('fileOwnMembers', () => {
        function ownMembersOf(code) {
            const { ast, services } = vueParser.parseForESLint(code, {
                filePath: '/ext/src/sw-x.override.vue',
                parser: tsParser,
                ecmaVersion: 2022,
                sourceType: 'module',
            });

            return [...fileOwnMembers({ ast, parserServices: services })].sort();
        }

        it('lists the bindings and props of a script setup block', () => {
            const code = [
                '<script setup lang="ts">',
                "import { computed, type Ref } from 'vue';",
                "import type { Order } from './types';",
                'const props = withDefaults(defineProps<{ order: Order; isLoading?: boolean }>(), {});',
                'const { total, list: [first] } = useSwPreviousState();',
                'function format() {}',
                '</script>',
            ].join('\n');

            expect(ownMembersOf(code)).toEqual(['computed', 'first', 'format', 'isLoading', 'order', 'props', 'total']);
        });

        it('lists the members of exported options', () => {
            const code = [
                '<script>',
                'export default defineComponent({',
                "    props: ['order'],",
                "    inject: ['acl'],",
                '    data() { return { total: 0 }; },',
                '    computed: { dateFilter() { return null; } },',
                '    setup() { return { format }; },',
                '});',
                '</script>',
            ].join('\n');

            expect(ownMembersOf(code)).toEqual(['acl', 'dateFilter', 'format', 'order', 'total']);
        });

        it('lists no top-level bindings of a plain script block', () => {
            expect(ownMembersOf("<script>\nimport dateFilter from './date';\nexport default {};\n</script>")).toEqual([]);
        });
    });
});
