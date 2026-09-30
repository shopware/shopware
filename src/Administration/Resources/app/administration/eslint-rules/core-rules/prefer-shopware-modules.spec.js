/**
 * @sw-package framework
 */

const RuleTester = require('eslint').RuleTester;
const rule = require('./prefer-shopware-modules');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

tester.run('prefer-shopware-modules', rule, {
    valid: [
        {
            name: 'a branch member that no family exports',
            code: `const value = Shopware.Utils.notAUtil();`,
        },
        {
            name: 'a branch this rule does not serve',
            code: `const factory = Shopware.Component.build('sw-foo');`,
        },
        {
            name: 'a store id that is only known at runtime',
            code: `const store = Shopware.Store.get(storeId);`,
        },
        {
            name: 'a mixin that MixinContainer does not declare',
            code: `const mixin = Shopware.Mixin.getByName('not-a-mixin');`,
        },
        {
            name: 'a read whose local name the file already binds',
            code: `import { createId } from 'somewhere';\nconst id = Shopware.Utils.createId();`,
        },
        {
            name: 'an unrelated global',
            code: `const x = Other.Utils.createId();`,
        },
        {
            name: 'an aliased branch used for something no specifier covers',
            code: `const { Mixin } = Shopware;\nMixin.register('my-mixin', {});`,
        },
        {
            name: 'a local that merely shares a branch name',
            code: `const Mixin = somethingElse;\nconst m = Mixin.getByName('sw-form-field');`,
        },
        {
            name: 'a root namespace import that is also used as a whole value stays',
            code: `import { string } from 'shopware:utils';\nconst a = string.kebabCase('x');\nfoo(string);`,
        },
        {
            name: 'a member name shadowed by a parameter stays on its namespace',
            code: `import { string } from 'shopware:utils';\nfunction f(kebabCase) { return string.kebabCase(kebabCase); }`,
        },
    ],
    invalid: [
        {
            name: 'a utils member read becomes a named import',
            code: `const id = Shopware.Utils.createId();`,
            output: `import { createId } from 'shopware:utils';\nconst id = createId();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a DAL class read becomes a named import',
            code: `const criteria = new Shopware.Data.Criteria(1, 25);`,
            output: `import { Criteria } from 'shopware:data';\nconst criteria = new Criteria(1, 25);`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a store lookup collapses into a composable call',
            code: `const store = Shopware.Store.get('swOrderDetail');`,
            output: `import useSwOrderDetailStore from 'shopware:stores/swOrderDetail';\nconst store = useSwOrderDetailStore();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a mixin lookup collapses into the value itself',
            code: `const mixin = Shopware.Mixin.getByName('sw-form-field');`,
            output: `import swFormFieldMixin from 'shopware:mixins/sw-form-field';\nconst mixin = swFormFieldMixin;`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a branch destructuring becomes one import',
            code: `const { Criteria, EntityCollection } = Shopware.Data;`,
            output: `import { Criteria, EntityCollection } from 'shopware:data';\n`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a namespace destructuring imports from that subpath',
            code: `const { warn } = Shopware.Utils.debug;`,
            output: `import { warn } from 'shopware:utils/debug';\n`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a renamed destructuring keeps the local name',
            code: `const { createId: makeId } = Shopware.Utils;`,
            output: `import { createId as makeId } from 'shopware:utils';\n`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a second rewrite reuses the import the first added',
            code: `const id = Shopware.Utils.createId();\nconst other = Shopware.Utils.createId();`,
            output: `import { createId } from 'shopware:utils';\nconst id = createId();\nconst other = createId();`,
            errors: [
                { messageId: 'preferModule' },
                { messageId: 'preferModule' },
            ],
        },
        {
            name: 'an aliased mixin lookup is rewritten and the dead alias removed',
            code: `const { Mixin } = Shopware;\nconst m = Mixin.getByName('sw-form-field');`,
            output: `import swFormFieldMixin from 'shopware:mixins/sw-form-field';\n\nconst m = swFormFieldMixin;`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'an aliased store lookup becomes a composable call',
            code: `const { Store } = Shopware;\nconst s = Store.get('swOrderDetail');`,
            output: `import useSwOrderDetailStore from 'shopware:stores/swOrderDetail';\n\nconst s = useSwOrderDetailStore();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'an aliased utils member is rewritten',
            code: `const { Utils } = Shopware;\nconst id = Utils.createId();`,
            output: `import { createId } from 'shopware:utils';\n\nconst id = createId();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'an alias with another reader keeps its binding',
            code: `const { Mixin } = Shopware;\nconst m = Mixin.getByName('sw-form-field');\nMixin.register('x', {});`,
            output: `import swFormFieldMixin from 'shopware:mixins/sw-form-field';\nconst { Mixin } = Shopware;\nconst m = swFormFieldMixin;\nMixin.register('x', {});`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a dead alias beside a live one loses only its own property',
            code: `const { Component, Mixin } = Shopware;\nconst m = Mixin.getByName('sw-form-field');\nComponent.register('x', {});`,
            output: `import swFormFieldMixin from 'shopware:mixins/sw-form-field';\nconst { Component } = Shopware;\nconst m = swFormFieldMixin;\nComponent.register('x', {});`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a renamed alias is followed',
            code: `const { Mixin: M } = Shopware;\nconst m = M.getByName('sw-form-field');`,
            output: `import swFormFieldMixin from 'shopware:mixins/sw-form-field';\n\nconst m = swFormFieldMixin;`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'the import goes above a doc comment, so it stays attached to its function',
            code: `/**\n * @constructor\n */\nexport default function make() {\n    return Shopware.Utils.createId();\n}`,
            output: `import { createId } from 'shopware:utils';\n\n/**\n * @constructor\n */\nexport default function make() {\n    return createId();\n}`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'the import follows the imports the file already has',
            code: `import template from './a.html.twig';\n\n/** Docs. */\nconst id = Shopware.Utils.createId();`,
            output: `import template from './a.html.twig';\nimport { createId } from 'shopware:utils';\n\n/** Docs. */\nconst id = createId();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a namespace member read imports the member from its subpath',
            code: `const x = Shopware.Utils.string.kebabCase('a');`,
            output: `import { kebabCase } from 'shopware:utils/string';\nconst x = kebabCase('a');`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'a root namespace import used only by member moves to the subpath',
            code: `import { string } from 'shopware:utils';\nconst x = string.kebabCase('a');\nconst y = string.camelCase('b');`,
            output: `import { kebabCase, camelCase } from 'shopware:utils/string';\nconst x = kebabCase('a');\nconst y = camelCase('b');`,
            errors: [{ messageId: 'preferSubpath' }, { messageId: 'preferSubpath' }],
        },
        {
            name: 'a root namespace import keeps its siblings',
            code: `import { createId, string } from 'shopware:utils';\nconst x = string.kebabCase(createId());`,
            output: `import { createId } from 'shopware:utils';\nimport { kebabCase } from 'shopware:utils/string';\nconst x = kebabCase(createId());`,
            errors: [{ messageId: 'preferSubpath' }],
        },
        {
            name: 'two namespaces with the same member: the second keeps its namespace',
            code: `const a = Shopware.Utils.format.md5('x');\nconst b = Shopware.Utils.string.md5('y');`,
            output: `import { md5 } from 'shopware:utils/format';\nimport { string } from 'shopware:utils';\nconst a = md5('x');\nconst b = string.md5('y');`,
            errors: [{ messageId: 'preferModule' }, { messageId: 'preferModule' }],
        },
        {
            name: 'a read the file already imports needs no new import',
            code: `import { Criteria } from 'shopware:data';\nconst c = new Shopware.Data.Criteria();`,
            output: `import { Criteria } from 'shopware:data';\nconst c = new Criteria();`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'an assignment to a namespace member keeps the namespace, which is the same object',
            code: `Shopware.Utils.string.kebabCase = () => '';`,
            output: `import { string } from 'shopware:utils';\nstring.kebabCase = () => '';`,
            errors: [{ messageId: 'preferModule' }],
        },
        {
            name: 'every namespace rewrite lands in one pass, whichever import is listed first',
            code: `import { format, array } from 'shopware:utils';\nconst a = array.slice([1], 1);\nconst b = format.currency(1, 'EUR');`,
            output: `import { currency } from 'shopware:utils/format';\nimport { slice } from 'shopware:utils/array';\nconst a = slice([1], 1);\nconst b = currency(1, 'EUR');`,
            errors: [{ messageId: 'preferSubpath' }, { messageId: 'preferSubpath' }],
        },
        {
            name: 'an existing import of the same specifier gains the name',
            code: `import { debug } from 'shopware:utils';\nconst id = Shopware.Utils.createId();`,
            output: `import { debug, createId } from 'shopware:utils';\nconst id = createId();`,
            errors: [{ messageId: 'preferModule' }],
        },
    ],
});

const tsTester = new RuleTester({
    languageOptions: {
        // eslint-disable-next-line import/no-extraneous-dependencies
        parser: require('typescript-eslint').parser,
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

tsTester.run('prefer-shopware-modules (TypeScript)', rule, {
    valid: [],
    invalid: [
        {
            name: 'the type keyword string is not a use of the string namespace',
            code: `import { string } from 'shopware:utils';\nfunction f(a: string): string {\n    return string.kebabCase(a);\n}`,
            output: `import { kebabCase } from 'shopware:utils/string';\nfunction f(a: string): string {\n    return kebabCase(a);\n}`,
            errors: [{ messageId: 'preferSubpath' }],
        },
    ],
});
