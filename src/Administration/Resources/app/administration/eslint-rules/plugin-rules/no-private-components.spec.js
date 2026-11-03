/**
 * @sw-package framework
 */

const fs = require('fs');
const { RuleTester } = require('eslint');
const rule = require('./no-private-components');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

const realReadFileSync = fs.readFileSync;
const registry = JSON.stringify({
    'shopware:components': { exports: [], subpaths: { 'sw-public-card': ['default'] } },
});

describe('no-private-components', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'readFileSync').mockImplementation((file, ...rest) =>
            String(file).endsWith('shopware-modules.json') ? registry : realReadFileSync(file, ...rest),
        );
    });

    ruleTester.run('no-private-components', rule, {
        valid: [
            {
                name: 'overrides a published component',
                code: "Shopware.Component.override('sw-public-card', {});",
            },
            {
                name: 'extends a published component under its own name',
                code: "Shopware.Component.extend('my-card', 'sw-public-card', {});",
            },
            {
                name: 'registers its own component',
                code: "Shopware.Component.register('my-card', {});",
            },
            {
                name: 'leaves a name it cannot read alone',
                code: 'Shopware.Component.override(name, {});',
            },
        ],
        invalid: [
            {
                name: 'overrides a component that is not published',
                code: "Shopware.Component.override('sw-cms-detail', {});",
                errors: [
                    {
                        message:
                            "'sw-cms-detail' is not public API of the Administration: an update can change or remove it without deprecation.",
                    },
                ],
            },
            {
                name: 'extends a component that is not published, through a destructured Component',
                code: "const { Component } = Shopware;\nComponent.extend('my-detail', 'sw-cms-detail', {});",
                errors: [{ messageId: 'notPublic' }],
            },
            {
                name: 'builds a component that is not published',
                code: "Shopware.Component.build('sw-cms-detail');",
                errors: [{ messageId: 'notPublic' }],
            },
        ],
    });
});
