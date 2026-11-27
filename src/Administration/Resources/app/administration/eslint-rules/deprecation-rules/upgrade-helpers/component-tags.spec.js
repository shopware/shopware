/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { readComponentCall, elementName, findAttribute, attributeRemovalRange, walk } = require('./component-tags');

const callRule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        return {
            CallExpression(node) {
                const call = readComponentCall(node);

                if (call) {
                    context.report({ node, message: `${call.method}:${call.name}:${call.target}:${call.options?.type ?? 'none'}` });
                }
            },
        };
    },
};

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('component-tags (calls)', callRule, {
    valid: [
        { name: 'ignores other calls', code: "Other.override('sw-x', {});" },
        { name: 'ignores computed members', code: "Component['override']('sw-x', {});" },
    ],
    invalid: [
        {
            name: 'reads override calls',
            code: "Shopware.Component.override('sw-order-list', {});",
            errors: [{ message: 'override:sw-order-list:sw-order-list:ObjectExpression' }],
        },
        {
            name: 'reads extend calls with the base as target',
            code: "Component.extend('acme-list', 'sw-order-list', {});",
            errors: [{ message: 'extend:acme-list:sw-order-list:ObjectExpression' }],
        },
        {
            name: 'reads lazy overrides without options object',
            code: "Component.override('sw-order-list', () => import('./x'));",
            errors: [{ message: 'override:sw-order-list:sw-order-list:ArrowFunctionExpression' }],
        },
        {
            name: 'reads build calls',
            code: "Component.build('sw-order-list');",
            errors: [{ message: 'build:sw-order-list:sw-order-list:none' }],
        },
    ],
});

const attributeRule = {
    meta: { type: 'problem', fixable: 'code', schema: [] },
    create(context) {
        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (elementName(node) !== 'sw-popover') {
                    return;
                }

                const attribute = findAttribute(node, 'resize-width');

                if (attribute) {
                    context.report({
                        node: attribute,
                        message: 'found',
                        fix: (fixer) => fixer.removeRange(attributeRemovalRange(context.sourceCode, attribute)),
                    });
                }
            },
        });
    },
};

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('component-tags (template)', attributeRule, {
    valid: [{ name: 'ignores elements without the attribute', code: '<template><sw-popover /></template>' }],
    invalid: [
        {
            name: 'finds bound kebab-case attributes and removes them with their whitespace',
            code: '<template><sw-popover :resize-width="true" /></template>',
            output: '<template><sw-popover /></template>',
            errors: [{ message: 'found' }],
        },
        {
            name: 'finds static camelCase attributes on PascalCase tags',
            code: '<template><SwPopover resizeWidth /></template>',
            output: '<template><SwPopover /></template>',
            errors: [{ message: 'found' }],
        },
    ],
});

describe('component-tags walk', () => {
    it('visits nested nodes but not parent links', () => {
        const child = { type: 'Child' };
        const root = { type: 'Root', body: [child], parent: { type: 'Parent' } };
        const visited = [];

        walk(root, (node) => visited.push(node.type));

        expect(visited).toEqual(['Root', 'Child']);
    });
});
