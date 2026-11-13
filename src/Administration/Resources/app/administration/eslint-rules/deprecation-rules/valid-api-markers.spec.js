/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./valid-api-markers');

const scriptTester = new RuleTester({
    languageOptions: {
        parser: require('@typescript-eslint/parser'),
    },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2021,
    },
});

scriptTester.run('valid-api-markers in scripts', rule, {
    valid: [
        {
            name: 'a deprecation with a version tag',
            code: `
                export default {
                    methods: {
                        /**
                         * @deprecated tag:v6.8.0 - Use {@link getSalesChannelCriteria} instead.
                         */
                        getLoadSalesChannelCriteria() {},
                    },
                };
            `,
        },
        {
            name: 'a deprecation in a line comment',
            code: `
                // @deprecated tag:v6.8.0 - Will be removed
                const legacy = true;
            `,
        },
        {
            name: 'a planned change with the keys of its kind',
            code: `
                export default {
                    methods: {
                        /**
                         * @bc-change ParameterRemoval version:v6.8.0 parameter:itemIndex
                         *   The template stops passing the index. Use this.lineItems.indexOf(item).
                         */
                        onDeleteItem(item, itemIndex) {},
                    },
                };
            `,
        },
        {
            name: 'a tag that is mentioned inside a sentence',
            code: `
                // Elements marked @deprecated or @bc-change are listed in the upgrade report
                const report = [];
            `,
        },
        {
            name: 'a marker-like string outside a comment',
            code: "const example = '@deprecated tag:broken';",
        },
    ],
    invalid: [
        {
            name: 'a deprecation without a version tag',
            code: `
/**
 * @deprecated Use getSalesChannelCriteria instead
 */
function getLoadSalesChannelCriteria() {}
            `,
            errors: [
                {
                    message: '@deprecated needs "tag:vX.Y.Z" directly after the tag name.',
                    line: 3,
                    column: 4,
                    endLine: 3,
                    endColumn: 51,
                },
            ],
        },
        {
            name: 'a four-part version',
            code: '// @deprecated tag:v6.8.0.0 - Will be removed',
            errors: [{ message: '"v6.8.0.0" is not a version of the form vX.Y.Z, for example v6.8.0.' }],
        },
        {
            name: 'a colon after the tag name',
            code: '/** @deprecated: tag:v6.8.0 - Replaced by resetRelatedStores */',
            errors: [{ message: '"@deprecated" must not be followed by ":".', line: 1, column: 5 }],
        },
        {
            name: 'a deprecation without a text',
            code: '/** @deprecated tag:v6.8.0 */',
            errors: [
                {
                    message: '@deprecated needs "- <text>" after the version, for example "- Use load instead".',
                },
            ],
        },
        {
            name: 'a typo in the tag name',
            code: '/** @bc-chnage BecomesPrivate version:v6.8.0 */',
            errors: [{ message: '"@bc-chnage" is not a tag, did you mean "@bc-change"?', line: 1, column: 5 }],
        },
        {
            name: 'an unknown kind',
            code: `
                /**
                 * @bc-change ParamterRemoval version:v6.8.0 parameter:itemIndex
                 */
                function onDeleteItem(item, itemIndex) {}
            `,
            errors: [{ message: '"ParamterRemoval" is not a @bc-change kind, did you mean "ParameterRemoval"?' }],
        },
        {
            name: 'every problem of one marker',
            code: '/** @bc-change ParameterRemoval variable:item */',
            errors: [
                { message: '@bc-change needs "version:vX.Y.Z".' },
                { message: 'ParameterRemoval needs "parameter:<value>".' },
                { message: 'ParameterRemoval has no key "variable", it takes: parameter.' },
            ],
        },
    ],
});

templateTester.run('valid-api-markers in templates', rule, {
    valid: [
        {
            name: 'markers in Twig comments',
            filename: 'test.html.twig',
            code: `
<template>
    {# @bc-change BlockMoved version:v6.8.0 parent:sw_example_card_header #}
    {% block sw_example_card_title %}
    {# @deprecated tag:v6.8.0 - Use the title prop instead #}
    <h3>{{ title }}</h3>
    {% endblock %}
</template>`,
        },
        {
            name: 'commented-out markup with a Vue listener',
            filename: 'test.html.twig',
            code: `
<template>
    <!-- Hidden until the field is validated
    <sw-email-field
        :value="currentValue"
        @change="onChange"
    />-->
</template>`,
        },
        {
            name: 'a marker in an HTML comment of a single-file component',
            filename: 'test.vue',
            code: `
<template>
    <!-- @deprecated tag:v6.8.0 - Use the title prop instead -->
    <h3>{{ title }}</h3>
</template>

<script setup>
/** @deprecated tag:v6.8.0 - Use the title prop instead */
const legacyTitle = 'title';
</script>`,
        },
    ],
    invalid: [
        {
            name: 'a Twig comment with a kind that lacks its key',
            filename: 'test.html.twig',
            code: `
<template>
    {# @bc-change BlockMoved version:v6.8.0 #}
    {% block sw_example_card_title %}{% endblock %}
</template>`,
            errors: [
                {
                    message: 'BlockMoved needs "parent:<value>".',
                    line: 3,
                    column: 8,
                    endLine: 3,
                    endColumn: 44,
                },
            ],
        },
        {
            name: 'a typo in a Twig comment',
            filename: 'test.html.twig',
            code: `
<template>
    {# @depreciated tag:v6.8.0 #}
    {% block sw_example_card_title %}{% endblock %}
</template>`,
            errors: [{ message: '"@depreciated" is not a tag, did you mean "@deprecated"?', line: 3 }],
        },
        {
            name: 'malformed markers in the template and the script of a single-file component',
            filename: 'test.vue',
            code: `
<template>
    <!-- @deprecated -->
    <h3>{{ title }}</h3>
</template>

<script setup>
/** @deprecated tag:6.8 - Use the title prop instead */
const legacyTitle = 'title';
</script>`,
            errors: [
                { message: '@deprecated needs "tag:vX.Y.Z" directly after the tag name.', line: 3 },
                { message: '"6.8" is not a version of the form vX.Y.Z, for example v6.8.0.', line: 8 },
            ],
        },
    ],
});
