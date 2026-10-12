const RuleTester = require('eslint').RuleTester;
const rule = require('./no-tc-translation');
const { describeWithSelectedDeprecationIds } = require('../deprecation-rules/registry/selected-deprecation-ids');

const HINT =
    '/* TODO Codemod: After the fixer replaces the call, verify pluralization and count interpolation manually. */ ';

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

tester.run('no-tc-translation', rule, {
    valid: [
        {
            name: 'this.$t() is allowed',
            code: `this.$t('some.translation.key');`,
        },
        {
            name: '$t() in template expression is allowed',
            code: `const label = $t('some.key', 2);`,
        },
        {
            name: 'unrelated method call',
            code: `this.someMethod();`,
        },
        {
            name: '$tc as a variable name is allowed',
            code: `const $tc = 'test';`,
        },
    ],
    invalid: [
        {
            name: 'this.$tc() should be this.$t()',
            code: `this.$tc('some.translation.key');`,
            output: `${HINT}this.$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'this.$tc() with count parameter',
            code: `this.$tc('some.translation.key', count);`,
            output: `${HINT}this.$t('some.translation.key', count);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() without this (template context)',
            code: `$tc('some.translation.key');`,
            output: `${HINT}$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() with count in template context',
            code: `$tc('some.translation.key', 3);`,
            output: `${HINT}$t('some.translation.key', 3);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'adds a TODO Codemod review hint in front of the whole member call when renaming $tc to $t',
            code: `const label = this.$tc('a.b', count);`,
            output: `const label = ${HINT}this.$t('a.b', count);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'multiple $tc calls in one file',
            code: `this.$tc('key.one'); this.$tc('key.two');`,
            output: `${HINT}this.$t('key.one'); ${HINT}this.$t('key.two');`,
            errors: [{ messageId: 'noTc' }, { messageId: 'noTc' }],
        },
    ],
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

templateTester.run('no-tc-translation in templates', rule, {
    valid: [
        {
            name: 'allows $t() in a bound template attribute',
            filename: 'test.html.twig',
            code: `<template><sw-button :label="$t('a.b')" /></template>`,
        },
    ],
    invalid: [
        {
            name: 'replaces $tc() with $t() in a bound template attribute',
            filename: 'test.html.twig',
            code: `<template><sw-button :label="$tc('a.b')" /></template>`,
            output: `<template><sw-button :label="${HINT}$t('a.b')" /></template>`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'adds a TODO Codemod review hint when renaming $tc to $t in a template interpolation',
            filename: 'test.vue',
            code: `<template><p>{{ $tc('a.b', count) }}</p></template>`,
            output: `<template><p>{{ ${HINT}$t('a.b', count) }}</p></template>`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'replaces $tc() with $t() in a v-if condition',
            filename: 'test.html.twig',
            code: `<template><div v-if="$tc('x')" /></template>`,
            output: `<template><div v-if="${HINT}$t('x')" /></template>`,
            errors: [{ messageId: 'noTc' }],
        },
    ],
});

describe('no-tc-translation message', () => {
    it('carries the registry description and the pluralization review hint', () => {
        const message = rule.meta.messages.noTc;

        expect(message).toContain('The $tc translation helper is deprecated.');
        expect(message).toContain('verify pluralization and count interpolation manually');
        expect(message).toContain('Removed in Shopware 6.9.0.');
    });
});

describeWithSelectedDeprecationIds('component.sw-alert', () => {
    tester.run('no-tc-translation with an unrelated deprecation id selected', rule, {
        valid: [
            {
                name: 'this.$tc() is not reported when only another migration is selected',
                code: `this.$tc('some.translation.key');`,
            },
        ],
        invalid: [],
    });
});
