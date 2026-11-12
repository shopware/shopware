/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { Linter } = require('eslint');
const { importingScripts, templateTargets, scriptsDeclare, templateReads, appendMember } = require('./override-templates');

describe('override-templates', () => {
    let directory;

    const contextFor = (file) => ({ physicalFilename: path.join(directory, file) });
    const write = (file, content) => {
        fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
        fs.writeFileSync(path.join(directory, file), content);
    };

    beforeEach(() => {
        directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-override-templates-'));
    });

    afterEach(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    it('finds every script in the folder that imports the template, not only index.js', () => {
        write('page/sw-order-list.js', "import template from './sw-order-list.html.twig';\nShopware.Component.override('sw-order-list', { template });");
        write('page/sw-order-list.spec.js', "import template from './sw-order-list.html.twig';");
        write('page/other.js', "import template from './other.html.twig';");

        expect(importingScripts(contextFor('page/sw-order-list.html.twig')).map(({ file }) => path.basename(file))).toEqual(['sw-order-list.js']);
        expect(templateTargets(contextFor('page/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
    });

    it('takes the target from the importing script', () => {
        write('acme/acme-order-list.ts', "import template from './acme-order-list.html.twig';\nComponent.extend('acme-order-list', 'sw-order-list', { template });");
        write('own/sw-order-list.js', "import template from './sw-order-list.html.twig';\nComponent.register('acme-own', { template });");
        write('extension/sw-order-list/index.js', "import template from './sw-order-list.html.twig';\nexport default { template };");

        expect(templateTargets(contextFor('acme/acme-order-list.html.twig'))).toEqual(['sw-order-list']);
        expect(templateTargets(contextFor('own/sw-order-list.html.twig'))).toEqual([]);
        expect(templateTargets(contextFor('extension/sw-order-list/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
    });

    it('falls back to twigTargets() without importing script', () => {
        expect(importingScripts(contextFor('missing/sw-order-list.html.twig'))).toEqual([]);
        expect(templateTargets(contextFor('missing/sw-order-list.html.twig'))).toEqual(['sw-order-list']);
    });

    it('finds members a script declares', () => {
        const scripts = [{ source: "methods: {\n    async getDelivery(order) {\n        return null;\n    },\n    other: () => this.transaction(order),\n}" }];

        expect(scriptsDeclare(scripts, 'getDelivery')).toBe(true);
        expect(scriptsDeclare(scripts, 'other')).toBe(true);
        expect(scriptsDeclare(scripts, 'transaction')).toBe(false);
    });

    it('reads template names outside of comments only', () => {
        expect(templateReads('{{ dateFilter(item.createdAt) }}', 'dateFilter')).toBe(true);
        expect(templateReads('{# dateFilter #}<!-- dateFilter -->{{ item.dateFilter }}', 'dateFilter')).toBe(false);
    });

    describe('appendMember', () => {
        const append = (code, options) => {
            const linter = new Linter();
            const rule = {
                meta: { fixable: 'code' },
                create: (context) => ({
                    'Program > ExpressionStatement > ObjectExpression'(node) {
                        context.report({ node, message: 'append', fix: appendMember(context.sourceCode, node, (unit) => ['acme() {', `${unit}return 1;`, '}'], options) });
                    },
                }),
            };

            const [{ fix }] = linter.verify(code, [{ plugins: { test: { rules: { append: rule } } }, rules: { 'test/append': 'error' } }]);

            return `${code.slice(0, fix.range[0])}${fix.text}${code.slice(fix.range[1])}`;
        };

        it('keeps the indentation and a trailing comma', () => {
            expect(append('({\n\tfoo: 1,\n});', { blankLine: true })).toBe('({\n\tfoo: 1,\n\n\tacme() {\n\t\treturn 1;\n\t},\n});');
        });

        it('adds no trailing comma where there was none', () => {
            expect(append('({\n    foo: 1\n});')).toBe('({\n    foo: 1,\n    acme() {\n        return 1;\n    }\n});');
        });

        it('keeps a comment at the end of the last member line', () => {
            expect(append('({\n    foo: 1, // keep\n});')).toBe('({\n    foo: 1, // keep\n    acme() {\n        return 1;\n    },\n});');
        });

        it('fills an empty object', () => {
            expect(append('({});')).toBe('({\n    acme() {\n        return 1;\n    },\n});');
        });
    });
});
