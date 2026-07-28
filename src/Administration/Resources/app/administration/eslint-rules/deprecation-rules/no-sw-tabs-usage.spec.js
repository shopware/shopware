/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-sw-tabs-usage');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2021,
    },
});

ruleTester.run('no-sw-tabs-usage', rule, {
    valid: [
        {
            name: 'allows empty files',
            filename: 'test.html.twig',
            code: '',
        },
        {
            name: 'allows mt-tabs',
            filename: 'test.html.twig',
            code: `
<template>
    <mt-tabs />
</template>`,
        },
        {
            name: 'allows standalone sw-tabs-item extension points',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-tabs-item />
</template>`,
        },
        {
            name: 'allows sw-tabs in v-else fallback after matching feature flag',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs />
    </template>

    <template v-else>
        <sw-tabs />
    </template>
</template>`,
        },
        {
            name: 'allows nested sw-tabs in v-else fallback after matching feature flag',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs />
    </template>

    <template v-else>
        <div>
            <sw-tabs />
        </div>
    </template>
</template>`,
        },
        {
            name: 'allows sw-tabs in negated feature flag branch',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="!feature.isActive('v6.8.0.0')">
        <sw-tabs />
    </template>
</template>`,
        },
        {
            name: 'allows sw-tabs in negated feature flag branch with double-quoted flag and parentheses',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if='!((feature.isActive("v6.8.0.0")))'>
        <sw-tabs />
    </template>
</template>`,
        },
        {
            name: 'allows sw-tabs in v-else fallback with double-quoted flag and parentheses',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if='(feature.isActive("v6.8.0.0"))'>
        <mt-tabs />
    </template>

    <template v-else>
        <sw-tabs />
    </template>
</template>`,
        },
        {
            name: 'allows sw-tabs with v-else after matching v-else-if feature branch',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="localeCount == 1">
        <mt-text-field />
    </template>

    <template v-else-if="feature.isActive('v6.8.0.0')">
        <mt-tabs />
    </template>

    <sw-tabs v-else />
</template>`,
        },
        {
            name: 'allows sw-tabs fallback when active branch combines a guard and the feature flag',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="hasTabs && feature.isActive('v6.8.0.0')">
        <mt-tabs v-if="hasTabs" />
    </template>

    <template v-else>
        <sw-tabs v-if="hasTabs" />
    </template>
</template>`,
        },
    ],
    invalid: [
        {
            name: 'disallows bare sw-tabs',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-tabs />
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
        {
            name: 'disallows sw-tabs in active feature flag branch',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <sw-tabs />
    </template>
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
        {
            name: 'disallows sw-tabs fallback paired with another feature flag',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="feature.isActive('FEATURE_NEXT_12345')">
        <mt-tabs />
    </template>

    <template v-else>
        <sw-tabs />
    </template>
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
        {
            name: 'disallows v-else fallback after negated matching feature flag',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="!feature.isActive('v6.8.0.0')">
        <mt-tabs />
    </template>

    <template v-else>
        <sw-tabs />
    </template>
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
        {
            name: 'disallows sw-tabs in v-else-if branch',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="feature.isActive('v6.8.0.0')">
        <mt-tabs />
    </template>

    <template v-else-if="someOtherCondition">
        <sw-tabs />
    </template>
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
        {
            name: 'disallows sw-tabs in arbitrary condition',
            filename: 'test.html.twig',
            code: `
<template>
    <template v-if="someOtherCondition">
        <sw-tabs />
    </template>
</template>`,
            errors: [{ messageId: 'noSwTabsUsage' }],
        },
    ],
});
