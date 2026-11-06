/**
 * @sw-package framework
 *
 * One spot per rule that must get a TODO comment, and one look-alike that must not.
 * The matchers themselves are covered by utils/todo-rule.spec.js.
 */

const { Linter } = require('eslint');
const rules = require('./todo-rules-6-8');

// The fixture paths are absolute, so the config has to match from the file system root.
const linter = new Linter({ configType: 'flat', cwd: '/' });

/**
 * @param {string} rule
 * @param {string} code
 * @param {string} filename
 */
function lint(rule, code, filename) {
    const isTemplate = /\.(vue|twig)$/.test(filename);
    const config = {
        files: ['**/*.js', '**/*.html.twig'],
        plugins: { sw: { rules } },
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            ...(isTemplate ? { parser: require('vue-eslint-parser') } : {}),
        },
        rules: { [`sw/${rule}`]: 'error' },
    };

    return {
        messages: linter.verify(code, config, { filename }),
        output: linter.verifyAndFix(code, config, { filename }).output,
    };
}

const SCRIPT = '/plugin/src/acme/index.js';

const cases = [
    ['no-private-composition-extension-api', 'Shopware.Component.overrideComponentSetup()("sw-x", () => ({}));', SCRIPT,
        'Shopware.Component.override("sw-x", {});', SCRIPT],
    ['no-vuex-state', 'Shopware.State.commit("acme/setFoo", 1);', SCRIPT,
        'Shopware.Store.get("acme").foo = 1;', SCRIPT],
    ['no-sw-text-editor', '<template>\n<sw-text-editor v-model:value="text" />\n</template>', '/plugin/src/acme/acme.html.twig',
        '<template>\n<mt-text-editor v-model="text" />\n</template>', '/plugin/src/acme/acme.html.twig'],
    ['no-order-detail-loading-members', 'export default { inject: ["swOrderDetailOnLoadingChange"] };', SCRIPT,
        'export default { inject: ["repositoryFactory"] };', SCRIPT],
    ['no-product-states', 'export default { computed: { s() { return this.productStates; } } };', '/plugin/src/sw-product-detail/index.js',
        'export default { computed: { s() { return this.productStates; } } };', '/plugin/src/acme-list/index.js'],
    ['no-system-config-config', 'this.systemConfigApiService.getConfig("Acme.config");', SCRIPT,
        'this.systemConfigApiService.getValues("Acme.config");', SCRIPT],
    ['no-axios-cancel-token', 'const source = axios.CancelToken.source();', SCRIPT,
        'const controller = new AbortController();', SCRIPT],
    ['no-product-advanced-mode', 'export default { computed: { tab() { return this.getModeSettingGeneralTab; } } };', '/plugin/src/sw-product-detail/index.js',
        'export default { computed: { tab() { return this.getModeSettingGeneralTab; } } };', '/plugin/src/acme-detail/index.js'],
    ['no-vite-7-config', 'export default { build: { rollupOptions: {} } };', '/plugin/src/acme/build.config.js',
        'export default { build: { rolldownOptions: {} } };', '/plugin/src/acme/config.js'],
    ['no-sw-help-sidebar', 'Shopware.Component.override("sw-help-sidebar", {});', SCRIPT,
        'Shopware.Component.override("sw-help-center-v2", {});', SCRIPT],
    ['no-sw-popover', '<template>\n<sw-popover :resize-width="true" />\n</template>', '/plugin/src/acme/acme.html.twig',
        '<template>\n<sw-popover-deprecated />\n</template>', '/plugin/src/acme/acme.html.twig'],
    ['no-custom-field-set-loaders', 'export default { created() { this.customFieldSetRepository.search(this.customFieldSetCriteria); } };', '/plugin/src/sw-manufacturer-detail/index.js',
        'export default { created() { this.customFieldSetRepository.search(this.customFieldSetCriteria); } };', '/plugin/src/sw-flow-detail/index.js'],
    ['no-removed-rule-conditions', 'Shopware.Component.extend("acme-condition", "sw-condition-line-item-in-category", {});', SCRIPT,
        'Shopware.Component.extend("acme-condition", "sw-condition-generic", {});', SCRIPT],
    ['no-automatic-promotion-toggle', 'this.orderService.toggleAutomaticPromotions(id, versionId, true);', SCRIPT,
        'this.orderService.applyAutomaticPromotions(id, versionId);', SCRIPT],
    ['no-unused-order-components', 'Shopware.Component.override("sw-order-user-card", {});', SCRIPT,
        'Shopware.Component.override("sw-order-general-info", {});', SCRIPT],
    ['no-order-detail-shipping-costs', 'export default { methods: { f() { this.onShippingChargeEdited(); } } };', '/plugin/src/sw-order-detail-details/index.js',
        'export default { methods: { f() { this.onShippingChargeEdited(); } } };', '/plugin/src/sw-order-detail-general/index.js'],
    ['no-worker-notification', 'Shopware.WorkerNotification.register("acme", {});', SCRIPT,
        'Shopware.Service("acme").register("acme", {});', SCRIPT],
    ['no-agentic-commerce-ui', 'export default { computed: { a() { return this.isAgenticCommerce; } } };', '/plugin/src/sw-sales-channel-detail-base/index.js',
        'export default { computed: { a() { return this.isAgenticCommerce; } } };', '/plugin/src/sw-sales-channel-list/index.js'],
    ['no-mail-template-preview-apis', 'this.mailService.testMailTemplate("a@b.c", template);', SCRIPT,
        'this.mailService.previewMailTemplate(templateType, template);', SCRIPT],
    ['no-media-grid-outside-click', '<template>\n<sw-media-grid @media-grid-selection-clear="clear" />\n</template>', '/plugin/src/acme/acme.html.twig',
        '<template>\n<sw-media-grid @media-item-selection-clear="clear" />\n</template>', '/plugin/src/acme/acme.html.twig'],
    ['no-cms-page-helpers', 'export default { methods: { f() { this.resetCmsPageState(); } } };', '/plugin/src/sw-cms-detail/index.js',
        'export default { methods: { f() { this.cmsPageState.resetCmsPageState(); } } };', '/plugin/src/sw-cms-detail/index.js'],
    ['no-removed-settings-product-promotion-members', 'export default { computed: { f() { return this.shippingRuleFilterCriteria; } } };', '/plugin/src/sw-settings-shipping-price-matrix/index.js',
        'export default { computed: { f() { return this.ruleFilterCriteria; } } };', '/plugin/src/sw-settings-shipping-price-matrix/index.js'],
    ['no-changed-default-behaviour', 'this.mailService.sendMailTemplate(to, name, template, media, salesChannelId);', SCRIPT,
        'this.mailService.sendMailTemplate(to, name, template, media, salesChannelId, false, [], { order });', SCRIPT],
];

describe('eslint-rules/deprecation-rules/todo-rules-6-8', () => {
    it('has a case for every rule', () => {
        expect(cases.map(([rule]) => rule).sort()).toEqual(Object.keys(rules).sort());
    });

    it.each(cases)('%s comments the affected spot once', (rule, code, filename) => {
        const { messages, output } = lint(rule, code, filename);

        expect(messages).toEqual([expect.objectContaining({ messageId: 'todo' })]);
        expect(output).toContain(`TODO Codemod: ${messages[0].message}`);
        expect(lint(rule, output, filename).output).toBe(output);
    });

    it.each(cases)('%s ignores the look-alike', (rule, ...[, , code, filename]) => {
        expect(lint(rule, code, filename).messages).toEqual([]);
    });
});
