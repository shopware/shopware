import template from './sw-settings-rule-detail-base.html.twig';

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'acl',
        'customFieldDataProviderService',
    ],

    emits: [
        'conditions-changed',
        'tree-finished-loading',
    ],

    props: {
        rule: {
            type: Object,
            required: true,
        },
        conditions: {
            type: Array,
            required: false,
            default: null,
        },
        conditionRepository: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        ruleNameError: {
            type: Object,
            required: false,
            default: null,
        },
        rulePriorityError: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currentConditions: null,
            customFieldSets: null,
            productStreamIndexingEnabled: null,
        };
    },

    computed: {
        availableModuleTypes() {
            return this.ruleConditionDataProviderService.getModuleTypes();
        },

        moduleTypes: {
            get() {
                return this.rule?.moduleTypes?.types ?? [];
            },

            set(value) {
                if (value.length === 0) {
                    this.rule.moduleTypes = null;
                    return;
                }

                this.rule.moduleTypes = { types: value };
            },
        },

        showCustomFields() {
            return this.rule && this.customFieldSets && this.customFieldSets.length > 0;
        },

        showProductStreamIndexingWarning() {
            return (
                this.productStreamIndexingEnabled === false &&
                this.conditions &&
                this.hasProductStreamConditions(this.conditions)
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadCustomFieldSets();
            this.loadProductStreamIndexingConfig();
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('rule').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        loadProductStreamIndexingConfig() {
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const headers = {
                headers: {
                    Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
                },
            };

            httpClient
                .get('/_admin/config-parameter/shopware.product_stream.indexing', headers)
                .then((response) => {
                    this.productStreamIndexingEnabled = response.data;
                })
                .catch(() => {
                    this.productStreamIndexingEnabled = true;
                });
        },

        hasProductStreamConditions(conditions) {
            return conditions.some((condition) => {
                if (condition.type === 'cartLineItemInProductStream') {
                    return true;
                }

                return (
                    condition.children &&
                    Array.isArray(condition.children) &&
                    this.hasProductStreamConditions(condition.children)
                );
            });
        },
    },
};
