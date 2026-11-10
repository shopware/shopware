/**
 * @sw-package framework
 *
 * A synthetic `api-usage.json` entry with an example of every usage type, so the specs of the rule
 * factory do not depend on the real 6.8 data. Every usage has its own message, which the specs match
 * reports by. Usages without `fix` have the default fix level of their type.
 */

module.exports = {
    id: 'api.test-entry',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Description of the test entry.',
    upgrade: ['UPGRADE-6.8.md#test-entry'],
    usage: [
        // member-usage
        { type: 'member-usage', object: 'Shopware', properties: ['State'], message: 'Shopware.State is removed.' },
        { type: 'member-usage', object: 'this', properties: ['$store'], message: 'this.$store is removed.' },
        { type: 'member-usage', properties: ['mapVuexState'], message: 'mapVuexState is removed.' },
        { type: 'member-usage', object: '*.mailTemplateType', properties: ['templateData'], message: 'templateData is removed.' },
        {
            type: 'member-usage',
            service: 'mailService',
            properties: ['sendMailTemplate'],
            withoutArgument: 2,
            message: 'Pass the template data.',
        },
        { type: 'member-usage', store: 'swProductDetail', properties: ['productStates'], message: 'The productStates getter is removed.' },
        { type: 'member-usage', object: 'this', properties: ['isScoped'], components: ['sw-order-list'], message: 'isScoped is removed.' },
        { type: 'member-usage', object: 'this.product', properties: ['states'], message: 'product.states is removed.' },

        // component-member
        {
            type: 'component-member',
            components: ['sw-order-detail'],
            names: [
                'isLoading',
                'onSaveAndReload',
            ],
            message: 'Use the swOrderDetail store.',
        },

        // replace-component-member
        {
            type: 'replace-component-member',
            components: ['sw-order-list'],
            name: 'dateFilter',
            with: "Shopware.Filter.getByName('date')",
            message: 'dateFilter is removed.',
            templateMessage: 'Add a dateFilter computed.',
        },
        {
            type: 'replace-component-member',
            fix: 'unsafe-auto',
            components: ['sw-order-list'],
            name: 'assetFilter',
            with: "Shopware.Filter.getByName('asset')",
            message: 'assetFilter is removed.',
            templateMessage: 'Add an assetFilter computed.',
        },

        // replace-component-method-call
        {
            type: 'replace-component-method-call',
            components: ['sw-order-list'],
            name: 'getDeliveryState',
            with: '{0}.primaryOrderDelivery?.stateMachineState',
            message: 'getDeliveryState() is removed.',
            strictNullMessage: 'getDeliveryState() is removed and its replacement is undefined instead of null.',
        },

        // inject-key
        { type: 'inject-key', names: ['swOrderDetailOnLoadingChange'], message: 'The inject key is removed.' },

        // removed-component
        { type: 'removed-component', names: ['sw-help-sidebar'], message: 'sw-help-sidebar is removed.' },
        { type: 'removed-component', names: ['sw-popover'], inTemplates: false, message: 'sw-popover is removed.' },

        // import-source
        { type: 'import-source', sources: ['vuex'], message: 'Vuex is removed.' },
        {
            type: 'import-source',
            sources: [
                'axios',
                'src/core/factory/http.factory',
            ],
            specifiers: ['CancelToken'],
            message: 'The CancelToken is removed.',
        },

        // string-literal
        { type: 'string-literal', values: ['is-physical'], message: 'Product states are removed.' },

        // object-key
        { type: 'object-key', names: ['cancelToken'], message: 'cancelToken is removed.' },
        { type: 'object-key', names: ['optimizeDeps'], files: '^vite\\.config\\.[cm]?[jt]s$', message: 'optimizeDeps changed.' },

        // template-attribute
        {
            type: 'template-attribute',
            components: ['sw-order-detail'],
            elements: ['router-view'],
            names: [
                'is-editing',
                '@loading-change',
            ],
            message: 'The router-view attributes are removed.',
        },
        { type: 'template-attribute', names: ['search-term'], message: 'searchTerm is removed.' },

        // module-snippets, without a message so the rule's own headline applies
        { type: 'module-snippets' },
    ],
};
