import template from './sw-custom-field-set-renderer.html.twig';
import './sw-custom-field-set-renderer.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description
 * Renders custom-field sets
 * @example-type code-only
 * @component-example
 */
Component.register('sw-custom-field-set-renderer', {
    template,

    inject: [
        'feature',
        'repositoryFactory',
    ],


    // Grant access to some variables to the child form render components
    provide() {
        return {
            getEntity: this.entity,
            getParentEntity: this.parentEntity,
            getCustomFieldSet: this.set,
            getCustomFieldSetVariant: this.variant,
        };
    },

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        sets: {
            type: Array,
            required: true,
        },
        entity: {
            type: Object,
            required: true,
        },
        parentEntity: {
            type: Object,
            required: false,
            default: null,
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
            },
        },
        disabled: {
            type: Boolean,
            default: false,
            required: false,
        },
        isLoading: {
            type: Boolean,
            default: false,
            required: false,
        },
        isSaveSuccessful: {
            type: Boolean,
            default: false,
            required: false,
        },
        showCustomFieldSetSelection: {
            type: Boolean,
            default: false,
            require: false,
        },
    },

    data() {
        return {
            customFields: {},
            loadingFields: [],
            tabWaitMaxAttempts: 10,
            tabWaitsAttempts: 0,
            refreshVisibleSets: false,
        };
    },

    computed: {
        hasParent() {
            return this.parentEntity ? !!this.parentEntity.id : false;
        },

        visibleCustomFieldSets() {
            return this.sortSets(this.sets);
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', this.entity.getEntityName()));
            criteria.addFilter(Criteria.equals('global', 0));
            criteria
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        globalCustomFieldSets() {
            return this.sets.filter((set) => set.global);
        },

        componentsWithMapInheritanceSupport() {
            return ['sw-field'];
        },
    },

    watch: {
        'entity.customFieldSetSelectionActive': {
            handler(value) {
                this.onChangeCustomFieldSetSelectionActive(value);
            },
            deep: true,
        },

        'entity.customFieldsSets': {
            handler() {
                this.onChangeCustomFieldSets();
            },
        },

        entity: {
            handler() {
                this.initializeCustomFields();
            },
            deep: true,
        },

        customFields: {
            handler(customFields) {
                // eslint-disable-next-line vue/no-mutating-props
                this.entity.customFields = customFields;
            },
            deep: true,
        },
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
            if (!this.entity.customFields && !this.entity.translated?.customFields) {
                return;
            }

            // Check if translated custom fields are available
            if (this.entity.translated?.customFields && Object.keys(this.entity.translated?.customFields).length <= 0) {
                return;
            }

            this.customFields = this.entity.translated?.customFields ?? this.entity.customFields;
        },

        getInheritedCustomField(customFieldName) {
            const value = this.parentEntity?.translated?.customFields?.[customFieldName] ?? null;

            if (value) {
                return value;
            }

            const customFieldInformation = this.getCustomFieldInformation(customFieldName);
            const customFieldType = customFieldInformation.type;

            switch (customFieldType) {
                case 'select': {
                    return [];
                }

                case 'bool': {
                    return false;
                }

                case 'html':
                case 'datetime':
                case 'text': {
                    return '';
                }

                case 'float':
                case 'int': {
                    return 0;
                }

                default: {
                    return null;
                }
            }
        },

        getCustomFieldInformation(customFieldName) {
            let returnValue;

            this.sets.some(set => set.customFields.some(customField => {
                const isMatching = customField.name === customFieldName;

                if (isMatching) {
                    returnValue = customField;
                }

                return isMatching;
            }));

            return returnValue;
        },

        getInheritValue(field) {
            // Search field in translated
            const value = this.parentEntity?.translated?.[field] ?? null;

            if (value) {
                return value;
            }

            // Search field on top level of entity
            return this.parentEntity?.[field] ?? null;
        },

        getParentCustomFieldSetSelectionSwitchState() {
            const parentEntity = this.parentEntity;

            if (parentEntity && parentEntity.hasOwnProperty('customFieldSets')) {
                return parentEntity.customFieldSets.length > 0;
            }

            return null;
        },

        supportsMapInheritance(customField) {
            const componentName = customField.config.componentName;

            if (customField.config.customFieldType === 'date') {
                return false;
            }

            return this.componentsWithMapInheritanceSupport.includes(componentName);
        },

        getBind(customField, props) {
            const customFieldClone = Shopware.Utils.object.cloneDeep(customField);

            if (customFieldClone.type === 'bool') {
                customFieldClone.config.bordered = true;
            }

            if (this.supportsMapInheritance(customFieldClone)) {
                customFieldClone.mapInheritance = props;

                return customFieldClone;
            }

            if (customFieldClone.config.customFieldType === 'entity' && customFieldClone.config.entity === 'product') {
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('options.group');

                customFieldClone.config.criteria = criteria;
                customFieldClone.config.displayVariants = true;
            }

            delete customFieldClone.config.label;
            delete customFieldClone.config.helpText;

            return customFieldClone;
        },

        getInheritWrapperBind(customField) {
            if (this.supportsMapInheritance(customField)) {
                return {};
            }

            return {
                helpText: this.getInlineSnippet(customField.config.helpText) || '',
                label: this.getInlineSnippet(customField.config.label) || ' ',
            };
        },

        customFieldSetCriteriaById() {
            const criteria = new Criteria(1, 1);

            criteria.getAssociation('customFields')
                .addSorting(Criteria.naturalSorting('config.customFieldPosition'));

            return criteria;
        },

        loadCustomFieldSet(setId) {
            if (this.loadingFields.includes(setId)) {
                // as we might triggered multiple times with the same item, we store the loading set in a heap cache
                return;
            }

            // failsave dealing with sets (should be an entityCollection, but in reality might be just an array)
            const set = this.sets.get ? this.sets.get(setId) : this.sets.find(s => s.id === setId);

            if (set.customFields && set.customFields.length > 0) {
                // already loaded, so do nothing
                return;
            }

            // indicate the loading of this item
            this.loadingFields.push(setId);

            // fully load the set
            this.customFieldSetRepository
                .get(setId, Shopware.Context.api, this.customFieldSetCriteriaById())
                .then(newSet => {
                    // replace the fully fetched set
                    this.sets.forEach((originalSet, index) => {
                        if (originalSet.id === newSet.id) {
                            this.$set(this.sets, index, newSet);
                        }
                    });

                    // remove the set from the currently loading onces and refresh the visible sets
                    this.loadingFields = this.loadingFields.filter(s => s.id !== setId);
                }).catch(() => {
                    // in case of error make loading again possible
                    this.loadingFields = this.loadingFields.filter(s => s.id !== setId);
                });
        },

        resetTabs() {
            if (this.visibleCustomFieldSets.length > 0 && this.$refs.tabComponent) {
                // Reset state of tab component if custom field selection changes
                this.$refs.tabComponent.mountedComponent();
                this.$refs.tabComponent.setActiveItem({ name: this.visibleCustomFieldSets[0].id });
            }
        },

        waitForTabComponent() {
            if (this.$refs.tabComponent || this.tabWaitsAttempts > this.tabWaitMaxAttempts) {
                return this.resetTabs();
            }
            // eslint-disable-next-line vue/valid-next-tick
            return this.$nextTick(() => {
                this.tabWaitsAttempts += 1;
                this.waitForTabComponent();
            });
        },

        getTabLabel(set) {
            if (set.config && this.getInlineSnippet(set.config.label)) {
                return this.getInlineSnippet(set.config.label);
            }

            return set.name;
        },

        onChangeCustomFieldSets(value, updateFn) {
            if (!this.$refs.tabComponent && (this.visibleCustomFieldSets.length > 0 || value)) {
                // when rendered initially we wait for the tabcomponent to load so we can activate the first item
                this.waitForTabComponent();
            } else {
                this.resetTabs();
            }

            if (typeof updateFn === 'function') {
                updateFn(value);
            }
        },

        onChangeCustomFieldSetSelectionActive(newVal) {
            this.onChangeCustomFieldSets();
            if (!newVal) {
                if (!this.entity.customFieldSets) {
                    this.initializeCustomFields();
                    return;
                }
                // eslint-disable-next-line vue/no-mutating-props
                this.entity.customFieldSets = this.entity.customFieldSets.filter(() => {
                    return false;
                });
            }
        },

        /**
         * @param { Array } sets
         */
        sortSets(sets) {
            return sets.sort((a, b) => a.position - b.position);
        },

        onUpdateActiveSelection(value) {
            this.$emit('change-active-selection', value);
        },
    },
});
