const RuleTester = require('eslint').RuleTester;
const rule = require('./no-tc-translation');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
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
        {
            name: 'Shopware.Snippet.t() is allowed',
            code: `Shopware.Snippet.t('some.key');`,
        },
        {
            name: 'tc() on other objects is allowed',
            code: `i18n.tc('some.key');`,
        },
        {
            name: 'named parameters before the plural count are allowed',
            code: `this.$t('some.key', { name: 'foo' }, 2);`,
        },
        {
            name: 'vue-i18n 10 translate options after the plural count are allowed',
            code: `this.$t('some.key', 2, { locale: 'de-DE' });`,
        },
        {
            name: 'default message with named parameters is allowed',
            code: `this.$t('some.key', 'Default message', { name: 'foo' });`,
        },
        {
            name: 'tc() on another Snippet object is allowed',
            code: `SomeLibrary.Snippet.tc('some.key');`,
        },
        {
            name: 'default message after a plural count is allowed',
            code: `this.$t('some.key', 2, 'Default message');`,
        },
        {
            name: 'non-literal arguments in an ambiguous order are allowed',
            code: `this.$t('some.key', params, count);`,
        },
        {
            name: 'spread options after a non-literal plural count are allowed',
            code: `this.$t('some.key', count, { ...options });`,
        },
    ],
    invalid: [
        {
            name: 'this.$tc() should be this.$t()',
            code: `this.$tc('some.translation.key');`,
            output: `this.$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'this.$tc() with count parameter',
            code: `this.$tc('some.translation.key', count);`,
            output: `this.$t('some.translation.key', count);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() without this (template context)',
            code: `$tc('some.translation.key');`,
            output: `$t('some.translation.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() with count in template context',
            code: `$tc('some.translation.key', 3);`,
            output: `$t('some.translation.key', 3);`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'multiple $tc calls in one file',
            code: `this.$tc('key.one'); this.$tc('key.two');`,
            output: `this.$t('key.one'); this.$t('key.two');`,
            errors: [{ messageId: 'noTc' }, { messageId: 'noTc' }],
        },
        {
            name: 'Shopware.Snippet.tc() should be Shopware.Snippet.t()',
            code: `Shopware.Snippet.tc('some.key');`,
            output: `Shopware.Snippet.t('some.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'optional chained Shopware.Snippet?.tc() should be Shopware.Snippet?.t()',
            code: `Shopware.Snippet?.tc('some.key');`,
            output: `Shopware.Snippet?.t('some.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'destructured Snippet.tc() should be Snippet.t()',
            code: `Snippet.tc('some.key');`,
            output: `Snippet.t('some.key');`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'legacy argument order of $t() is swapped',
            code: `this.$t('some.key', count, { name: 'foo' });`,
            output: `this.$t('some.key', { name: 'foo' }, count);`,
            errors: [{ messageId: 'legacyArgumentOrder' }],
        },
        {
            name: 'legacy argument order of $root.$t() is swapped',
            code: `this.$root.$t('some.key', 1, { count: amount, total });`,
            output: `this.$root.$t('some.key', { count: amount, total }, 1);`,
            errors: [{ messageId: 'legacyArgumentOrder' }],
        },
        {
            name: 'legacy argument order of Shopware.Snippet.t() is swapped',
            code: `Shopware.Snippet.t('some.key', 0, { entityName });`,
            output: `Shopware.Snippet.t('some.key', { entityName }, 0);`,
            errors: [{ messageId: 'legacyArgumentOrder' }],
        },
        {
            name: 'legacy argument order with named parameters from a variable is reported without autofix',
            code: `this.$t('some.key', 1, params);`,
            output: null,
            errors: [{ messageId: 'legacyArgumentOrderManual' }],
        },
        {
            name: 'legacy argument order with a named parameter fallback is reported without autofix',
            code: `this.$t(translationKey, 1, this.formatParameters(error.parameters) || {});`,
            output: null,
            errors: [{ messageId: 'legacyArgumentOrderManual' }],
        },
        {
            name: '$tc() with the legacy argument order is renamed and swapped',
            code: `this.$tc('some.key', items.length, {\n    name: item.name,\n});`,
            output: `this.$t('some.key', {\n    name: item.name,\n}, items.length);`,
            errors: [{ messageId: 'legacyArgumentOrder' }, { messageId: 'noTc' }],
        },
        {
            name: 'legacy argument order with spread named parameters is reported without autofix',
            code: `this.$t('some.key', 2, { ...params });`,
            output: null,
            errors: [{ messageId: 'legacyArgumentOrderManual' }],
        },
        {
            name: 'legacy argument order with a computed key is reported without autofix',
            code: `this.$t('some.key', 2, { [key]: value });`,
            output: null,
            errors: [{ messageId: 'legacyArgumentOrderManual' }],
        },
        {
            name: 'spread next to translate options is reported without autofix',
            code: `this.$t('some.key', 2, { ...params, locale: 'de-DE' });`,
            output: null,
            errors: [{ messageId: 'legacyArgumentOrderManual' }],
        },
        {
            name: 'spread next to a named parameter is swapped',
            code: `this.$t('some.key', 2, { ...params, name });`,
            output: `this.$t('some.key', { ...params, name }, 2);`,
            errors: [{ messageId: 'legacyArgumentOrder' }],
        },
        {
            name: 'locale of $tc() becomes a translate option',
            code: `this.$tc('some.key', 2, 'de-DE');`,
            output: `this.$t('some.key', 2, { locale: 'de-DE' });`,
            errors: [{ messageId: 'legacyTcLocale' }, { messageId: 'noTc' }],
        },
        {
            name: 'template literal locale of Shopware.Snippet.tc() becomes a translate option',
            code: 'Shopware.Snippet.tc(\'some.key\', count, `${language}-${region}`);',
            output: 'Shopware.Snippet.t(\'some.key\', count, { locale: `${language}-${region}` });',
            errors: [{ messageId: 'legacyTcLocale' }, { messageId: 'noTc' }],
        },
        {
            name: 'locale and named parameters of $tc() become named parameters and translate options',
            code: `this.$tc('some.key', count, 'de-DE', { name });`,
            output: `this.$t('some.key', { name }, { plural: count, locale: 'de-DE' });`,
            errors: [{ messageId: 'legacyTcLocaleNamed' }, { messageId: 'noTc' }],
        },
        {
            name: 'locale of $tc() from a variable is reported without autofix',
            code: `this.$tc('some.key', 2, locale);`,
            output: `this.$t('some.key', 2, locale);`,
            errors: [{ messageId: 'legacyArgumentOrderManual' }, { messageId: 'noTc' }],
        },
    ],
});

templateTester.run('no-tc-translation (template)', rule, {
    valid: [
        {
            name: '$t() in a template is allowed',
            code: `<template><div>{{ $t('some.key') }}</div></template>`,
        },
    ],
    invalid: [
        {
            name: '$tc() in a mustache should be $t()',
            code: `<template><div>{{ $tc('some.key') }}</div></template>`,
            output: `<template><div>{{ $t('some.key') }}</div></template>`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: '$tc() in a bound attribute should be $t()',
            code: `<template><mt-button :label="$tc('some.key', 2)" /></template>`,
            output: `<template><mt-button :label="$t('some.key', 2)" /></template>`,
            errors: [{ messageId: 'noTc' }],
        },
        {
            name: 'legacy argument order in a template is swapped',
            code: `<template><div>{{ $t('some.key', count, { name }) }}</div></template>`,
            output: `<template><div>{{ $t('some.key', { name }, count) }}</div></template>`,
            errors: [{ messageId: 'legacyArgumentOrder' }],
        },
    ],
});

templateTester.run('no-tc-translation (twig template)', rule, {
    valid: [],
    invalid: [
        {
            name: '$tc() in a bound attribute of a twig template should be $t()',
            filename: 'sw-foo.html.twig',
            code: `<template>{% block sw_foo %}<mt-button :label="$tc('some.key', count, { name })" />{% endblock %}</template>`,
            output: `<template>{% block sw_foo %}<mt-button :label="$t('some.key', { name }, count)" />{% endblock %}</template>`,
            errors: [{ messageId: 'noTc' }, { messageId: 'legacyArgumentOrder' }],
        },
    ],
});
