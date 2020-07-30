import template from './sw-custom-field-set-renderer.html.twig';
import './sw-custom-field-set-renderer.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description
 * Renders custom-field sets
 * @example-type code-only
 * @component-example
 */
Component.register('sw-custom-field-set-renderer', {
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('placeholder')
    ],

    // Grant access to some variables to the child form render components
    provide() {
        return {
            getEntity: this.entity,
            getParentEntity: this.parentEntity,
            getCustomFieldSet: this.set,
            getCustomFieldSetVariant: this.variant
        };
    },

    props: {
        sets: {
            type: Array,
            required: true
        },
        entity: {
            type: Object,
            required: true
        },
        parentEntity: {
            type: Object,
            required: false,
            default: null
        },
        variant: {
            type: String,
            required: false,
            default: 'tabs',
            validValues: ['tabs', 'media-collapse'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['tabs', 'media-collapse'].includes(value);
            }
        },
        disabled: {
            type: Boolean,
            default: false,
            required: false
        },
        isLoading: {
            type: Boolean,
            default: false,
            required: false
        },
        isSaveSuccessful: {
            type: Boolean,
            default: false,
            required: false
        },
        showCustomFieldSetSelection: {
            type: Boolean,
            default: false,
            require: false
        }
    },

    data() {
        return {
        };
    },

    computed: {
        hasParent() {
            return this.parentEntity ? !!this.parentEntity.id : false;
        },

        // Check if it is possible to use custom field set selection
        customFieldSetSelectionAvailable() {
            return this.showCustomFieldSetSelection
                && this.entity.hasOwnProperty('customFieldSets')
                && this.entity.hasOwnProperty('customFieldSetSelectionActive');
        },

        filterCustomFields() {
            if (!this.customFieldSetSelectionAvailable) {
                return false;
            }

            if (this.entity.customFieldSetSelectionActive === false) {
                return false;
            }

            // Main product with customFieldSetSelectionActive not set
            if (this.entity.customFieldSetSelectionActive === null && !this.hasParent) {
                return false;
            }

            // customFieldSetSelectionActive not set and parent product has no selection
            if (this.entity.customFieldSetSelectionActive === null
                && this.getInheritValue('customFieldSetSelectionActive') === null) {
                return false;
            }

            return true;
        },

        visibleCustomFieldSets() {
            if (!this.filterCustomFields) {
                return this.sets;
            }

            return this.sets.filter(set => {
                // Return custom field sets of parent if current state is inherited
                if (this.hasParent && this.entity.customFieldSets.length < 1) {
                    return this.parentEntity.customFieldSets.has(set.id) || set.global;
                }

                return this.entity.customFieldSets.has(set.id) || set.global;
            });
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addFilter(Criteria.equals('relations.entityName', this.entity.getEntityName()));
            criteria.addFilter(Criteria.equals('global', 0));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        globalCustomFieldSets() {
            return this.sets.filter((set) => set.global);
        }
    },

    watch: {
        'entity.customFieldSetSelectionActive': {
            handler(value) {
                this.onChangeCustomFieldSetSelectionActive(value);
            },
            deep: true
        },

        'entity.customFieldsSets': {
            handler() {
                this.onChangeCustomFieldSets();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeCustomFields();
            this.onChangeCustomFieldSets();
        },

        initializeCustomFields() {
            if (!this.entity.customFields) {
                this.entity.customFields = {};
            }
        },

        getInheritedCustomField(customFieldName) {
            return utils.get(this.parentEntity, `translated.customFields.${customFieldName}`, null);
        },

        getInheritValue(field) {
            // Search field in translated
            const value = utils.get(this.parentEntity, `translated.${field}`, null);

            if (value) {
                return value;
            }

            // Search field on top level of entity
            return utils.get(this.parentEntity, `${field}`, null);
        },

        getParentCustomFieldSetSelectionSwitchState() {
            const parentEntity = this.parentEntity;

            if (parentEntity && parentEntity.hasOwnProperty('customFieldSets')) {
                return parentEntity.customFieldSets.length > 0;
            }

            return null;
        },

        getBind(customField) {
            const customFieldClone = Shopware.Utils.object.cloneDeep(customField);
            delete customFieldClone.config.label;
            delete customFieldClone.config.helpText;

            return customFieldClone;
        },

        onChangeCustomFieldSets(value, updateFn) {
            if (this.visibleCustomFieldSets.length > 0 && this.$refs.tabComponent) {
                // Reset state of tab component if custom field selection changes
                this.$nextTick(() => {
                    this.$refs.tabComponent.mountedComponent();
                    this.$refs.tabComponent.setActiveItem(this.visibleCustomFieldSets[0]);
                });
            }

            if (typeof updateFn === 'function') {
                updateFn(value);
            }
        },

        onChangeCustomFieldSetSelectionActive(newVal) {
            this.onChangeCustomFieldSets();
            if (!newVal) {
                this.entity.customFieldSets = this.entity.customFieldSets.filter(() => {
                    return false;
                });
            }
        }
    }
});
