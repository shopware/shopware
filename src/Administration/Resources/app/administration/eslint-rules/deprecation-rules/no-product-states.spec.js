/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-product-states');
const data = require('./no-product-states.data');

const member = (name) => data.members.find((entry) => entry.name === name).message;
const literal = (value) => [...data.literals, ...data.stateValues].find((entry) => entry.value === value).message;
const STATES = data.statesProperty.message;
const scriptTodo = (text) => `// TODO Codemod(6.8, product-states): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, product-states): ${text} #}`;

/** Each `[line, message]` pair: a line of the code, which the rule marks with `message` above it. */
const invalidScript = (name, ...pairs) => ({
    name,
    code: pairs.map(([line]) => line).join('\n'),
    output: pairs.flatMap(([line, message]) => [scriptTodo(message), line]).join('\n'),
    errors: pairs.map(([, message]) => ({ message })),
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-product-states (script)', rule, {
    valid: [
        { name: 'ignores the states of a country', code: 'const count = country.states.length + this.country.states.length;' },
        { name: 'ignores the states of a tax rule', code: 'const states = this.taxRule.data.states;' },
        {
            name: 'ignores country states inside product overrides',
            code: "Component.override('sw-product-detail-base', { computed: { a() { return this.selectedCountry.states; } } });",
        },
        { name: 'ignores the product type', code: "const digital = this.productType === 'digital' || product.type === 'digital';" },
        { name: 'ignores snippet keys that contain a state', code: "this.$t('sw-product.variations.generatedListColumnContentProductState.is-download');" },
    ],
    invalid: [
        {
            name: 'marks productStates once per line in an override',
            code: [
                "Shopware.Component.override('sw-product-detail-base', {",
                '    computed: {',
                '        isDownloadCardVisible() {',
                "            return this.productStates.includes('is-download');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Shopware.Component.override('sw-product-detail-base', {",
                '    computed: {',
                '        isDownloadCardVisible() {',
                `            ${scriptTodo(member('productStates'))}`,
                "            return this.productStates.includes('is-download');",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('productStates') }],
        },
        invalidScript('marks creationStates in the route query', [
            "this.$router.push({ name: 'sw.product.create', query: { creationStates: ['is-download'] } });",
            member('creationStates'),
        ]),
        invalidScript('marks the store getter', ["const states = Shopware.Store.get('swProductDetail').productStates;", member('productStates')]),
        invalidScript('marks writes on variants', ["item.productStates = ['is-download'];", member('productStates')]),
        invalidScript(
            'marks the states of products and variants',
            ["const digital = this.product.states.includes('is-download');", STATES],
            ['variant.states = [];', STATES],
        ),
        invalidScript('marks any states inside overrides of sw-product-stream components', [
            "Component.override('sw-product-stream-value', { computed: { a() { return this.actualCondition.states; } } });",
            STATES,
        ]),
        invalidScript(
            'marks the filter key and the rule condition type',
            ["this.defaultFilters.push('product-states-filter');", literal('product-states-filter')],
            ["const condition = { type: 'cartLineItemProductStates' };", literal('cartLineItemProductStates')],
        ),
        invalidScript('marks state values on their own', ["const physical = 'is-physical';", literal('is-physical')]),
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-product-states (template)', rule, {
    valid: [
        {
            name: 'ignores the states of a country',
            filename: '/plugin/src/sw-settings-country/sw-settings-country.html.twig',
            code: '<template>\n    <span>{{ country.states.length }}</span>\n</template>',
        },
        {
            name: 'ignores the product type',
            filename: '/plugin/src/sw-product-detail-base/sw-product-detail-base.html.twig',
            code: "<template>\n    <div v-if=\"productType === 'digital'\" />\n</template>",
        },
    ],
    invalid: [
        {
            name: 'marks the productStates prop of sw-product-variants-overview',
            filename: '/plugin/src/acme-variants/acme-variants.html.twig',
            code: "<template>\n    <sw-product-variants-overview :product-states=\"['all']\" />\n</template>",
            output: [
                '<template>',
                `    ${twigTodo(member('productStates'))}`,
                "    <sw-product-variants-overview :product-states=\"['all']\" />",
                '</template>',
            ].join('\n'),
            errors: [{ message: member('productStates') }],
        },
        {
            name: 'marks product.states once per line',
            filename: '/plugin/src/sw-product-detail-base/sw-product-detail-base.html.twig',
            code: "<template>\n    <div v-if=\"product.states.includes('is-download')\" />\n</template>",
            output: [
                '<template>',
                `    ${twigTodo(STATES)}`,
                "    <div v-if=\"product.states.includes('is-download')\" />",
                '</template>',
            ].join('\n'),
            errors: [{ message: STATES }],
        },
        {
            name: 'marks unique names in any template',
            filename: '/plugin/src/acme-stream/acme-stream.html.twig',
            code: '<template>\n    <sw-single-select :options="productStateOptions" />\n</template>',
            output: [
                '<template>',
                `    ${twigTodo(member('productStateOptions'))}`,
                '    <sw-single-select :options="productStateOptions" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: member('productStateOptions') }],
        },
    ],
});
