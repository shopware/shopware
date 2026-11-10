/**
 * @sw-package framework
 *
 * Synthetic `apiUsageMigrations` for the no-deprecated-api-usage specs, so they do not depend on the
 * real registry data. Every step has its own message, which the specs match reports by.
 */

function migration(id, usage) {
    return {
        id,
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: `Description of ${id}.`,
        usage,
    };
}

const apiUsageMigrations = [
    migration('test.member-usage', [
        { kind: 'member-usage', fix: 'manual', object: 'Shopware', properties: ['State'], message: 'Shopware.State is removed.' },
        { kind: 'member-usage', fix: 'manual', object: 'this', properties: ['$store'], message: 'this.$store is removed.' },
        { kind: 'member-usage', fix: 'manual', properties: ['mapVuexState'], message: 'mapVuexState is removed.' },
        {
            kind: 'member-usage',
            fix: 'manual',
            object: '*.mailTemplateType',
            properties: ['templateData'],
            message: 'templateData is removed.',
        },
        {
            kind: 'member-usage',
            fix: 'manual',
            service: 'mailService',
            properties: ['sendMailTemplate'],
            withoutArgument: 2,
            message: 'Pass the template data.',
        },
        {
            kind: 'member-usage',
            fix: 'manual',
            store: 'swProductDetail',
            properties: ['productStates'],
            message: 'The productStates getter is removed.',
        },
        {
            kind: 'member-usage',
            fix: 'manual',
            object: 'this',
            properties: ['isScoped'],
            components: ['sw-order-list'],
            message: 'isScoped is removed.',
        },
        { kind: 'member-usage', fix: 'manual', object: 'this.product', properties: ['states'], message: 'product.states is removed.' },
    ]),
    migration('test.component-member', [
        {
            kind: 'component-member',
            fix: 'manual',
            components: ['sw-order-detail'],
            names: [
                'isLoading',
                'onSaveAndReload',
            ],
            message: 'Use the swOrderDetail store.',
        },
    ]),
    migration('test.replace-member', [
        {
            kind: 'replace-component-member',
            fix: 'auto',
            components: ['sw-order-list'],
            name: 'dateFilter',
            with: "Shopware.Filter.getByName('date')",
            message: 'dateFilter is removed.',
            templateMessage: 'Add a dateFilter computed.',
        },
        {
            kind: 'replace-component-member',
            fix: 'unsafe-auto',
            components: ['sw-order-list'],
            name: 'assetFilter',
            with: "Shopware.Filter.getByName('asset')",
            message: 'assetFilter is removed.',
            templateMessage: 'Add an assetFilter computed.',
        },
    ]),
    migration('test.replace-method-call', [
        {
            kind: 'replace-component-method-call',
            fix: 'auto',
            components: ['sw-order-list'],
            name: 'getDeliveryState',
            with: '{0}.primaryOrderDelivery?.stateMachineState',
            message: 'getDeliveryState() is removed.',
            strictNullMessage: 'getDeliveryState() is removed and its replacement is undefined instead of null.',
        },
    ]),
    migration('test.inject-key', [
        { kind: 'inject-key', fix: 'manual', names: ['swOrderDetailOnLoadingChange'], message: 'The inject key is removed.' },
    ]),
    migration('test.removed-component', [
        { kind: 'removed-component', fix: 'manual', names: ['sw-help-sidebar'], message: 'sw-help-sidebar is removed.' },
        {
            kind: 'removed-component',
            fix: 'manual',
            names: ['sw-popover'],
            inTemplates: false,
            message: 'sw-popover is removed.',
        },
    ]),
    migration('test.import-source', [
        { kind: 'import-source', fix: 'manual', sources: ['vuex'], message: 'Vuex is removed.' },
        {
            kind: 'import-source',
            fix: 'manual',
            sources: [
                'axios',
                'src/core/factory/http.factory',
            ],
            specifiers: ['CancelToken'],
            message: 'The CancelToken is removed.',
        },
    ]),
    migration('test.string-literal', [
        { kind: 'string-literal', fix: 'manual', values: ['is-physical'], message: 'Product states are removed.' },
    ]),
    migration('test.object-key', [
        { kind: 'object-key', fix: 'manual', names: ['cancelToken'], message: 'cancelToken is removed.' },
        {
            kind: 'object-key',
            fix: 'manual',
            names: ['optimizeDeps'],
            files: '^vite\\.config\\.[cm]?[jt]s$',
            message: 'optimizeDeps changed.',
        },
    ]),
    migration('test.template-attribute', [
        {
            kind: 'template-attribute',
            fix: 'manual',
            components: ['sw-order-detail'],
            elements: ['router-view'],
            names: [
                'is-editing',
                '@loading-change',
            ],
            message: 'The router-view attributes are removed.',
        },
        { kind: 'template-attribute', fix: 'manual', names: ['search-term'], message: 'searchTerm is removed.' },
    ]),
    migration('test.module-snippets', [
        { kind: 'module-snippets', fix: 'auto' },
    ]),
];

module.exports = {
    apiUsageMigrations,
};
