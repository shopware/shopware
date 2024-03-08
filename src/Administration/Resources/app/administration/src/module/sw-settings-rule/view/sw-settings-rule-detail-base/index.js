import template from './sw-settings-rule-detail-base.html.twig';

/**
 * @private
 * @package services-settings
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadCustomFieldSets();
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('rule').then((sets) => {
                this.customFieldSets = sets;
            });
        },
    },
};
