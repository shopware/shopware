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
            name: 'an existing import of the same specifier gains the name',
            code: `import { debug } from 'shopware:utils';\nconst id = Shopware.Utils.createId();`,
            output: `import { debug, createId } from 'shopware:utils';\nconst id = createId();`,
            errors: [{ messageId: 'preferModule' }],
        },
    ],
});
