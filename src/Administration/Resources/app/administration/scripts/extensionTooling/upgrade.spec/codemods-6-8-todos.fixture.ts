/**
 * @sw-package framework
 *
 * The cases of `codemods-6-8-todos.spec.ts`: for every comment-only 6.8 codemod, realistic extension files and the
 * spots the upgrade marks in them.
 *
 * Not a spec file: no `.spec.ts` suffix, so Jest does not execute it.
 */

export interface UpgradeCase {
    /** Registry id of the codemod. */
    id: string;
    behaviour: string;
    /** The extension files by path below the administration folder of the plugin. */
    files: Record<string, string[]>;
    /**
     * The spots the report lists as manual work, as `<file>:<line>` after the upgrade, sorted.
     * Scripts report the marked line, templates the line of the codemod comment above it.
     */
    spots: string[];
}

export const TODO_CODEMOD_CASES: UpgradeCase[] = [
    {
        id: 'service-calls',
        behaviour: 'marks updateAdmin(), validationService.email() and previewPattern() above their calls',
        files: {
            'src/service/acme-integration.service.js': [
                'const { Service } = Shopware;',
                '',
                'export default class AcmeIntegrationService {',
                '    constructor(numberRangeService) {',
                '        this.numberRangeService = numberRangeService;',
                '    }',
                '',
                '    async grantAdminAccess(integration) {',
                "        await Service('integrationService').updateAdmin(integration.id, true);",
                '    }',
                '',
                '    isValidContactEmail(email) {',
                "        return Service('validationService').email(email);",
                '    }',
                '',
                '    previewOrderNumber(pattern) {',
                "        return this.numberRangeService.previewPattern('order', pattern, 1);",
                '    }',
                '}',
            ],
        },
        spots: [
            'src/service/acme-integration.service.js:10',
            'src/service/acme-integration.service.js:15',
            'src/service/acme-integration.service.js:20',
        ],
    },
    {
        id: 'composition-api-private',
        behaviour: 'marks overrideComponentSetup() with the .override.vue file of its target',
        files: {
            'src/extension/sw-product-list/index.js': [
                "import { computed } from 'vue';",
                '',
                "Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState) => {",
                '    const productColumns = computed(() => [',
                '        ...previousState.productColumns.value,',
                "        { property: 'customFields.acme_rating', label: 'acme.product.rating' },",
                '    ]);',
                '',
                '    return { productColumns };',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-product-list/index.js:4',
        ],
    },
    {
        id: 'vuex-state',
        behaviour: 'marks Shopware.State with the Pinia store that replaces the Vuex module',
        files: {
            'src/component/acme-flow-panel/index.js': [
                'export default {',
                '    computed: {',
                '        flow() {',
                "            return Shopware.State.get('swFlowState').flow;",
                '        },',
                '',
                '        isConsentGiven() {',
                "            return Shopware.State.get('usageData').isConsentGiven;",
                '        },',
                '    },',
                '',
                '    methods: {',
                '        setTriggerActions(actions) {',
                "            Shopware.State.commit('swFlowState/setTriggerActions', actions);",
                '        },',
                '    },',
                '};',
            ],
        },
        spots: [
            'src/component/acme-flow-panel/index.js:10',
            'src/component/acme-flow-panel/index.js:17',
            'src/component/acme-flow-panel/index.js:5',
        ],
    },
    {
        id: 'axios-v1',
        behaviour: 'marks CancelToken and the cancelToken option in an API service',
        files: {
            'src/service/api/acme-sync.api.service.js': [
                'const ApiService = Shopware.Classes.ApiService;',
                '',
                'export default class AcmeSyncApiService extends ApiService {',
                '    constructor(httpClient, loginService) {',
                "        super(httpClient, loginService, 'acme-sync');",
                "        this.name = 'acmeSyncApiService';",
                '    }',
                '',
                '    sync(payload) {',
                '        this.cancelSource = this.httpClient.CancelToken.source();',
                '',
                "        return this.httpClient.post('/_action/acme-sync', payload, {",
                '            headers: this.getBasicHeaders(),',
                '            cancelToken: this.cancelSource.token,',
                '        });',
                '    }',
                '}',
            ],
        },
        spots: [
            'src/service/api/acme-sync.api.service.js:11',
            'src/service/api/acme-sync.api.service.js:16',
        ],
    },
    {
        id: 'vite-8',
        behaviour: 'marks the own Vite config at its top and default imports of CommonJS-only packages',
        files: {
            'vite.config.mts': [
                "import { defineConfig } from 'vite';",
                '',
                'export default defineConfig({',
                '    build: {',
                '        sourcemap: true,',
                '    },',
                '});',
            ],
            'src/component/acme-search/index.js': [
                "import debounce from 'lodash/debounce';",
                '',
                'export default {',
                '    created() {',
                '        this.onSearch = debounce(this.onSearch, 300);',
                '    },',
                '',
                '    methods: {',
                '        onSearch(term) {',
                '            this.$router.replace({ query: { term } });',
                '        },',
                '    },',
                '};',
            ],
        },
        spots: [
            'src/component/acme-search/index.js:2',
            'vite.config.mts:2',
        ],
    },
    {
        id: 'worker-notification',
        behaviour: 'marks the queue stats flag and Shopware.WorkerNotification',
        files: {
            'src/init/acme-indexing-notification.init.js': [
                'const { WorkerNotification } = Shopware;',
                '',
                'if (Shopware.Context.app.config.adminWorker?.enableQueueStatsWorker) {',
                "    WorkerNotification.register('AcmeIndexingMessage', {",
                "        name: 'Acme\\\\IndexingMessage',",
                '        fn: (next) => next(),',
                '    });',
                '}',
            ],
        },
        spots: [
            'src/init/acme-indexing-notification.init.js:4',
            'src/init/acme-indexing-notification.init.js:6',
        ],
    },
    {
        id: 'mail-template-api',
        behaviour: 'marks a removed member and service method in an override of sw-mail-template-detail',
        files: {
            'src/extension/sw-mail-template-detail/index.js': [
                "Shopware.Component.override('sw-mail-template-detail', {",
                '    computed: {',
                '        hasTemplateData() {',
                "            return this.$super('hasTemplateData') || this.acmeHasTemplateData;",
                '        },',
                '    },',
                '',
                '    methods: {',
                '        async onSendAcmeTestMail() {',
                '            await this.mailService.testMailTemplate(',
                '                this.testerMail,',
                '                this.mailTemplate,',
                '                this.mailTemplateMedia,',
                '                this.testMailSalesChannelId,',
                '            );',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-mail-template-detail/index.js:12',
            'src/extension/sw-mail-template-detail/index.js:4',
        ],
    },
    {
        id: 'cms-helpers',
        behaviour: 'marks resetCmsPageState() in an override of sw-cms-detail once, not its $super call',
        files: {
            'src/extension/sw-cms-detail/index.js': [
                "Shopware.Component.override('sw-cms-detail', {",
                '    methods: {',
                '        resetCmsPageState() {',
                "            this.$super('resetCmsPageState');",
                "            Shopware.Store.get('acmeCmsExtras').$reset();",
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-cms-detail/index.js:4',
        ],
    },
    {
        id: 'order-detail-store',
        behaviour: 'marks the injected loading provide and its calls in an override of a view',
        files: {
            'src/extension/sw-order-detail-general/index.js': [
                "Shopware.Component.override('sw-order-detail-general', {",
                "    inject: ['swOrderDetailOnLoadingChange', 'acmeRecalculationService'],",
                '',
                '    methods: {',
                '        async onAcmeRecalculate() {',
                '            this.swOrderDetailOnLoadingChange(true);',
                '            await this.acmeRecalculationService.recalculate(this.order.id);',
                '            this.swOrderDetailOnLoadingChange(false);',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-order-detail-general/index.js:11',
            'src/extension/sw-order-detail-general/index.js:3',
            'src/extension/sw-order-detail-general/index.js:8',
        ],
    },
    {
        id: 'order-promotion-toggle',
        behaviour: 'marks the old switch binding in a template override of sw-order-promotion-field',
        files: {
            'src/extension/sw-order-promotion-field/index.js': [
                "import template from './sw-order-promotion-field.html.twig';",
                '',
                "Shopware.Component.override('sw-order-promotion-field', {",
                '    template,',
                '});',
            ],
            'src/extension/sw-order-promotion-field/sw-order-promotion-field.html.twig': [
                '{% block sw_order_promotion_field_switch %}',
                '<mt-switch',
                '    v-model="disabledAutoPromotions"',
                '    class="acme-promotion-field__switch"',
                '    :label="$t(\'acme.order.disableAutomaticPromotions\')"',
                '    :disabled="!acl.can(\'order.editor\')"',
                '/>',
                '{% endblock %}',
            ],
        },
        spots: [
            'src/extension/sw-order-promotion-field/sw-order-promotion-field.html.twig:2',
        ],
    },
    {
        id: 'order-unused-components',
        behaviour: 'marks an override of a removed order component',
        files: {
            'src/extension/sw-order-user-card/index.js': [
                "Shopware.Component.override('sw-order-user-card', {",
                '    computed: {',
                '        hasDifferentBillingAndShippingAddress() {',
                "            return this.$super('hasDifferentBillingAndShippingAddress') && !this.acmeHideAddresses;",
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-order-user-card/index.js:2',
        ],
    },
    {
        id: 'order-shipping-costs',
        behaviour: 'marks onShippingChargeEdited() in an override of sw-order-detail-details',
        files: {
            'src/extension/sw-order-detail-details/index.js': [
                "Shopware.Component.override('sw-order-detail-details', {",
                '    methods: {',
                '        onShippingChargeEdited(amount) {',
                "            this.$super('onShippingChargeEdited', amount);",
                '            this.acmeShippingTracker.track(this.order.id, amount);',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-order-detail-details/index.js:4',
        ],
    },
    {
        id: 'product-states',
        behaviour: 'marks productStates and its state value once per line in an override',
        files: {
            'src/extension/sw-product-detail-base/index.js': [
                "Shopware.Component.override('sw-product-detail-base', {",
                '    computed: {',
                '        showAcmeLicenseCard() {',
                "            return this.productStates.includes('is-download');",
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-product-detail-base/index.js:5',
        ],
    },
    {
        id: 'system-config-schema',
        behaviour: 'marks a decorator that replaces systemConfigApiService.getConfig()',
        files: {
            'src/init/acme-system-config.init.js': [
                "const acmeCard = { name: 'acmeExtras', title: 'Acme extras', elements: [] };",
                '',
                "Shopware.Application.addServiceProviderDecorator('systemConfigApiService', (service) => {",
                '    const getConfig = service.getConfig.bind(service);',
                '',
                '    service.getConfig = async (domain, ...args) => {',
                '        const cards = await getConfig(domain, ...args);',
                '',
                "        return domain === 'AcmeUpgrade.config' ? [...cards, acmeCard] : cards;",
                '    };',
                '',
                '    return service;',
                '});',
            ],
        },
        spots: [
            'src/init/acme-system-config.init.js:7',
        ],
    },
    {
        id: 'product-advanced-mode',
        behaviour: 'marks an override of an advanced mode member of sw-product-detail',
        files: {
            'src/extension/sw-product-detail/index.js': [
                "Shopware.Component.override('sw-product-detail', {",
                '    computed: {',
                '        getModeSettingGeneralTab() {',
                '            return [',
                "                ...this.$super('getModeSettingGeneralTab'),",
                "                { key: 'acme_license', label: 'acme.product.licenseCard', enabled: true, name: 'general' },",
                '            ];',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-product-detail/index.js:4',
        ],
    },
    {
        id: 'custom-field-set-loaders',
        behaviour: 'marks the loader computed with the entity name of the component',
        files: {
            'src/extension/sw-manufacturer-detail/index.js': [
                "Shopware.Component.override('sw-manufacturer-detail', {",
                '    computed: {',
                '        customFieldSetCriteria() {',
                "            const criteria = this.$super('customFieldSetCriteria');",
                '',
                "            criteria.addFilter(Shopware.Data.Criteria.prefix('name', 'acme_'));",
                '',
                '            return criteria;',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-manufacturer-detail/index.js:4',
        ],
    },
    {
        id: 'rule-condition-components',
        behaviour: 'marks the component of a registered rule condition',
        files: {
            'src/init/acme-rule-conditions.init.js': [
                "Shopware.Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {",
                "    ruleConditionService.addCondition('acmeLineItemNet', {",
                "        component: 'sw-condition-is-net-select',",
                "        label: 'acme.rule.lineItemNet',",
                "        scopes: ['lineItem'],",
                "        group: 'item',",
                '    });',
                '',
                '    return ruleConditionService;',
                '});',
            ],
        },
        spots: [
            'src/init/acme-rule-conditions.init.js:4',
        ],
    },
    {
        id: 'agentic-commerce-ui',
        behaviour: 'marks isAgenticCommerce in a template override of sw-sales-channel-detail-base',
        files: {
            'src/extension/sw-sales-channel-detail-base/index.js': [
                "import template from './sw-sales-channel-detail-base.html.twig';",
                '',
                "Shopware.Component.override('sw-sales-channel-detail-base', {",
                '    template,',
                '});',
            ],
            'src/extension/sw-sales-channel-detail-base/sw-sales-channel-detail-base.html.twig': [
                '{% block sw_sales_channel_detail_agentic_commerce_tracking %}',
                '{% parent %}',
                '',
                '<mt-card',
                '    v-if="isAgenticCommerce"',
                '    position-identifier="acme-agentic-commerce-feed"',
                '    :title="$t(\'acme.salesChannel.agenticFeed\')"',
                '>',
                '    <acme-agentic-feed-settings :sales-channel="salesChannel" />',
                '</mt-card>',
                '{% endblock %}',
            ],
        },
        spots: [
            'src/extension/sw-sales-channel-detail-base/sw-sales-channel-detail-base.html.twig:4',
        ],
    },
    {
        id: 'media-grid-deselect',
        behaviour: 'marks the media-grid-selection-clear listener on sw-media-grid',
        files: {
            'src/component/acme-media-picker/index.js': [
                "import template from './acme-media-picker.html.twig';",
                '',
                'export default {',
                '    template,',
                '',
                '    data() {',
                '        return { selection: [] };',
                '    },',
                '',
                '    methods: {',
                '        onClearSelection() {',
                '            this.selection = [];',
                '        },',
                '    },',
                '};',
            ],
            'src/component/acme-media-picker/acme-media-picker.html.twig': [
                '{% block acme_media_picker %}',
                '<sw-media-grid',
                '    class="acme-media-picker"',
                '    presentation="medium-preview"',
                '    @media-grid-selection-clear="onClearSelection"',
                '>',
                '    <slot></slot>',
                '</sw-media-grid>',
                '{% endblock %}',
            ],
        },
        spots: [
            'src/component/acme-media-picker/acme-media-picker.html.twig:2',
        ],
    },
    {
        id: 'settings-product-promotion-members',
        behaviour: 'marks the removed ruleFilter in a template override of its component',
        files: {
            'src/extension/sw-promotion-v2-cart-condition-form/index.js': [
                "import template from './sw-promotion-v2-cart-condition-form.html.twig';",
                '',
                "Shopware.Component.override('sw-promotion-v2-cart-condition-form', {",
                '    template,',
                '});',
            ],
            'src/extension/sw-promotion-v2-cart-condition-form/sw-promotion-v2-cart-condition-form.html.twig': [
                '{% block sw_promotion_v2_cart_condition_form_rules_field %}',
                '<sw-select-rule-create',
                '    v-if="promotion"',
                '    v-model:rules="promotion.cartRules"',
                '    class="acme-promotion-cart-rules"',
                '    :local-mode="promotion.isNew()"',
                '    :rule-filter="ruleFilter"',
                "    :rule-scope=\"['checkout', 'global', 'lineItem', 'acme']\"",
                '    :disabled="isEditingDisabled"',
                '/>',
                '{% endblock %}',
            ],
        },
        spots: [
            'src/extension/sw-promotion-v2-cart-condition-form/sw-promotion-v2-cart-condition-form.html.twig:2',
        ],
    },
    {
        id: 'flag-gated-behaviour',
        behaviour: 'marks the media library sorting that an override declares',
        files: {
            'src/extension/sw-media-library/index.js': [
                "Shopware.Component.override('sw-media-library', {",
                '    data() {',
                '        return {',
                '            sorting: {',
                "                sortBy: 'fileName',",
                "                sortDirection: 'asc',",
                '            },',
                '            acmeShowPrivate: false,',
                '        };',
                '    },',
                '',
                '    computed: {',
                '        nextMediaCriteria() {',
                "            const criteria = this.$super('nextMediaCriteria');",
                '',
                '            if (!this.acmeShowPrivate) {',
                "                criteria.addFilter(Shopware.Data.Criteria.equals('private', false));",
                '            }',
                '',
                '            return criteria;',
                '        },',
                '    },',
                '});',
            ],
        },
        spots: [
            'src/extension/sw-media-library/index.js:15',
            'src/extension/sw-media-library/index.js:5',
        ],
    },
];
