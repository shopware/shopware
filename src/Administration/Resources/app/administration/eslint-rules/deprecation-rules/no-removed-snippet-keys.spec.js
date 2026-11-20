/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-snippet-keys');

const TAX_DISPLAY = 'Snippet key sw-settings-customer-group.detail.fieldDisplayGrossLabel is removed. '
    + 'Use sw-settings-customer-group.detail.taxDisplay.label instead.';
const GROSS = 'Snippet key global.sw-condition.condition.cartTaxDisplay.gross is removed. '
    + 'Use global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross instead.';
const DISCOUNT = 'Snippet key global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge is removed. '
    + 'Use global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion instead.';
const FIXED = 'Snippet key global.sw-condition.condition.promotionCodeOfTypeRule.fixed is removed. '
    + 'Use global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.fixed instead.';
const TOOLTIP = 'Snippet key sw-import-export.profile.addNewProfileTooltipLanguage is removed without replacement.';

const lines = (...rows) => rows.join('\n');
const template = (...rows) => lines('<template>', ...rows, '</template>');

const scriptTester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });
const templateTester = new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022, sourceType: 'module' } });

scriptTester.run('no-removed-snippet-keys (scripts)', rule, {
    valid: [
        {
            name: 'keeps the keys the core still uses',
            code: lines(
                "this.$t('global.sw-condition.condition.cartTaxDisplay.label');",
                "this.$t('global.sw-condition.condition.lineItemOfTypeRule.label');",
                "this.$t('global.sw-condition.condition.dayOfWeekRule');",
            ),
        },
        {
            name: 'ignores strings that only contain a key',
            code: lines(
                "const prefix = 'global.sw-condition.condition.cartTaxDisplay.gross.hint';",
                'const key = `global.sw-condition.condition.cartTaxDisplay.${type}`;',
            ),
        },
    ],
    invalid: [
        {
            name: 'replaces renamed keys in translation calls and plain strings',
            code: lines(
                "const label = this.$tc('sw-settings-customer-group.detail.fieldDisplayGrossLabel');",
                "const gross = Shopware.Snippet.t(\"global.sw-condition.condition.cartTaxDisplay.gross\");",
                'const options = [{ value: \'promotion\', label: `global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge` }];',
            ),
            output: lines(
                "const label = this.$tc('sw-settings-customer-group.detail.taxDisplay.label');",
                "const gross = Shopware.Snippet.t(\"global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross\");",
                'const options = [{ value: \'promotion\', label: `global.sw-condition-generic.cartLineItemOfType.lineItemType.options.promotion` }];',
            ),
            errors: [{ message: TAX_DISPLAY }, { message: GROSS }, { message: DISCOUNT }],
        },
        {
            name: 'marks keys without replacement',
            code: "const tooltip = this.$t('sw-import-export.profile.addNewProfileTooltipLanguage');",
            output: lines(
                `// TODO Codemod(6.8, snippet-keys): ${TOOLTIP}`,
                "const tooltip = this.$t('sw-import-export.profile.addNewProfileTooltipLanguage');",
            ),
            errors: [{ message: TOOLTIP }],
        },
    ],
});

templateTester.run('no-removed-snippet-keys (templates)', rule, {
    valid: [
        {
            name: 'keeps the new keys',
            filename: 'acme.html.twig',
            code: template("{{ $t('sw-settings-customer-group.detail.taxDisplay.label') }}"),
        },
    ],
    invalid: [
        {
            name: 'replaces renamed keys in interpolations, bound and static attributes of .html.twig templates',
            filename: 'acme.html.twig',
            code: template(
                '{% block acme_tax_display %}',
                "    <mt-switch :label=\"$t('sw-settings-customer-group.detail.fieldDisplayGrossLabel')\" />",
                '    <acme-option label="global.sw-condition.condition.promotionCodeOfTypeRule.fixed" />',
                "    {{ $tc('global.sw-condition.condition.cartTaxDisplay.gross') }}",
                '{% endblock %}',
            ),
            output: template(
                '{% block acme_tax_display %}',
                "    <mt-switch :label=\"$t('sw-settings-customer-group.detail.taxDisplay.label')\" />",
                '    <acme-option label="global.sw-condition-generic.promotionCodeOfType.promotionCodeType.options.fixed" />',
                "    {{ $tc('global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.gross') }}",
                '{% endblock %}',
            ),
            errors: [{ message: TAX_DISPLAY }, { message: FIXED }, { message: GROSS }],
        },
        {
            name: 'marks keys without replacement above the element',
            filename: 'acme.vue',
            code: template(
                '    <sw-help-text',
                "        :text=\"$t('sw-import-export.profile.addNewProfileTooltipLanguage')\"",
                '    />',
            ),
            output: template(
                `    <!-- TODO Codemod(6.8, snippet-keys): ${TOOLTIP} -->`,
                '    <sw-help-text',
                "        :text=\"$t('sw-import-export.profile.addNewProfileTooltipLanguage')\"",
                '    />',
            ),
            errors: [{ message: TOOLTIP }],
        },
    ],
});
