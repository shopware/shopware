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
            name: '$tc() with the legacy argument order is renamed and swapped',
            code: `this.$tc('some.key', items.length, {\n    name: item.name,\n});`,
            output: `this.$t('some.key', {\n    name: item.name,\n}, items.length);`,
            errors: [{ messageId: 'legacyArgumentOrder' }, { messageId: 'noTc' }],
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
    valid: [
        {
            name: '$t() in a twig mustache is allowed',
            filename: 'sw-foo.html.twig',
            code: `<template>{% block sw_foo %}<div>{{ $t('some.key') }}</div>{% endblock %}</template>`,
        },
        {
            name: '$tc outside of a twig mustache is ignored',
            filename: 'sw-foo.html.twig',
            code: `<template><p>Use $tc('key') no longer</p></template>`,
        },
    ],
    invalid: [
        {
            name: '$tc() in a twig mustache should be $t()',
            filename: 'sw-foo.html.twig',
            code: `<template>{% block sw_foo %}<div>{{ $tc('some.key') }} - {{ $tc('other.key', 2) }}</div>{% endblock %}</template>`,
            output: `<template>{% block sw_foo %}<div>{{ $t('some.key') }} - {{ $t('other.key', 2) }}</div>{% endblock %}</template>`,
            errors: [{ messageId: 'noTc', column: 37 }, { messageId: 'noTc', column: 61 }],
        },
        {
            name: '$tc() in a multi-line twig mustache and a bound attribute should be $t()',
            filename: 'sw-foo.html.twig',
            code: `<template>\n<mt-button :label="$tc('a')">\n    {{\n        $root.$tc('b')\n    }}\n</mt-button>\n</template>`,
            output: `<template>\n<mt-button :label="$t('a')">\n    {{\n        $root.$t('b')\n    }}\n</mt-button>\n</template>`,
            errors: [{ messageId: 'noTc', line: 2 }, { messageId: 'noTc', line: 4 }],
        },
    ],
});
