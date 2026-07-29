import { computed } from 'vue';

import { mapInheritanceSlotPropsToMeteorProps } from 'src/core/service/utils/meteor-inheritance.utils';

import template from './sw-custom-field-set-renderer.html.twig';
import './sw-custom-field-set-renderer.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description
 * Renders custom-field sets
 * @example-type code-only
 * @component-example
 */
export default {
    template,

    inject: [
        'feature',
        'repositoryFactory',
    ],

    // Grant access to some variables to the child form render components
    provide() {
        return {
            getEntity: computed(() => this.entity),
            getParentEntity: computed(() => this.parentEntity),
            getCustomFieldSet: computed(() => this.set),
            getCustomFieldSetVariant: computed(() => this.variant),
        };
    },

    emits: [
        'process-finish',
        'save',
        'change-active-selection',
    ],

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
            validValues: [
                'tabs',
                'media-collapse',
            ],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return [
                    'tabs',
                    'media-collapse',
                ].includes(value);
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
            indirectInheritedCustomFields: null,
            loadingFields: [],
            tabWaitMaxAttempts: 10,
            tabWaitsAttempts: 0,
            refreshVisibleSets: false,
            translatedInheritanceLoadKey: null,
        };
    },

    computed: {
        hasParent() {
            return this.hasExplicitParentEntity || this.usesTranslatedInheritance;
        },

        hasExplicitParentEntity() {
            return !!this.parentEntity?.id;
        },

        usesTranslatedInheritance() {
            return (
                !this.hasExplicitParentEntity &&
                !!this.entity?.id &&
                typeof this.entity?.getEntityName === 'function' &&
                !!this.translatedInheritanceSourceLanguageId
            );
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
            criteria.addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        globalCustomFieldSets() {
            return this.sets.filter((set) => set.global);
        },

        componentsWithMapInheritanceSupport() {
            return [
                'sw-text-field',
                'sw-textarea-field',
                'sw-select-field',
                'sw-checkbox-field',
                'sw-switch-field',
                'mt-switch',
                'sw-number-field',
                'sw-datepicker',
                'sw-email-field',
                'mt-email-field',
                'sw-url-field',
                'sw-password-field',
                'sw-radio-field',
                'sw-colorpicker',
                'mt-colorpicker',
                'sw-compact-colorpicker',
                'sw-price-field',
                'sw-tagged-field',
                // for backwards compatibility with old custom fields
                'sw-field',
            ];
        },

        translatedInheritanceSourceLanguageId() {
            const language = Shopware.Store.get('context')?.api?.language;
            const parentLanguageId = language?.parentId;

            if (parentLanguageId) {
                return parentLanguageId;
            }

            if (Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId) {
                return null;
            }

            return Shopware.Context.api.systemLanguageId;
        },
    },

    watch: {
        translatedInheritanceSourceLanguageId() {
            this.loadInheritedCustomFields();
        },

        sets: {
            handler() {
                this.loadInheritedCustomFields();
            },
            deep: true,
        },

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
                this.loadInheritedCustomFields();
            },
            deep: true,
        },

        customFields: {
            handler(customFields) {
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
            this.loadInheritedCustomFields();
            this.onChangeCustomFieldSets();
        },

        initializeCustomFields() {
            if (!this.entity.customFields) {
                return;
            }

            this.customFields = this.entity.customFields;
        },

        hasOverriddenTranslatedCustomFields() {
            return Object.values(this.customFields ?? {}).some((value) => value !== null && value !== undefined);
        },

        hasInheritedTranslatedCustomFields() {
            return this.sets.some((set) => {
                return set.customFields?.some((customField) => this.isInheritedTranslatedCustomField(customField.name));
            });
        },

        hasInheritedTranslatedCustomFieldsWithoutFallback() {
            return this.sets.some((set) => {
                return set.customFields?.some((customField) => {
                    if (!this.isInheritedTranslatedCustomField(customField.name)) {
                        return false;
                    }

                    const translatedValue = this.entity?.translated?.customFields?.[customField.name];

                    return translatedValue === null || translatedValue === undefined;
                });
            });
        },

        resetTranslatedInheritanceState() {
            this.indirectInheritedCustomFields = null;
            this.translatedInheritanceLoadKey = null;
        },

        getTranslatedInheritanceLoadKey() {
            return [
                this.entity.getEntityName(),
                this.entity.id,
                this.translatedInheritanceSourceLanguageId,
            ].join(':');
        },

        getTranslatedInheritanceContext() {
            return {
                ...Shopware.Context.api,
                languageId: this.translatedInheritanceSourceLanguageId,
            };
        },

        isInheritedTranslatedCustomField(customFieldName) {
            return this.customFields?.[customFieldName] === null || this.customFields?.[customFieldName] === undefined;
        },

        getInheritedCustomFields(customFieldName) {
            const parentCustomFields = this.parentEntity?.translated?.customFields;

            if (parentCustomFields) {
                return parentCustomFields?.[customFieldName];
            }

            if (!this.usesTranslatedInheritance || !this.isInheritedTranslatedCustomField(customFieldName)) {
                return this.indirectInheritedCustomFields?.[customFieldName];
            }

            if (Object.hasOwn(this.indirectInheritedCustomFields ?? {}, customFieldName)) {
                return this.indirectInheritedCustomFields?.[customFieldName];
            }

            return this.entity?.translated?.customFields?.[customFieldName];
        },

        getDefaultInheritedCustomFieldValue(customFieldName) {
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

        async loadInheritedCustomFields() {
            if (!this.usesTranslatedInheritance) {
                this.resetTranslatedInheritanceState();

                return;
            }

            const loadKey = this.getTranslatedInheritanceLoadKey();

            if (!this.hasOverriddenTranslatedCustomFields() && !this.hasInheritedTranslatedCustomFields()) {
                if (this.translatedInheritanceLoadKey !== loadKey) {
                    this.resetTranslatedInheritanceState();
                }

                return;
            }

            if (this.translatedInheritanceLoadKey === loadKey) {
                return;
            }

            this.translatedInheritanceLoadKey = loadKey;

            try {
                const inheritedEntity = await this.repositoryFactory
                    .create(this.entity.getEntityName())
                    .get(this.entity.id, this.getTranslatedInheritanceContext());

                if (this.translatedInheritanceLoadKey !== loadKey) {
                    return;
                }

                this.indirectInheritedCustomFields = inheritedEntity?.customFields ?? null;
            } catch (error) {
                console.error(error);

                if (this.translatedInheritanceLoadKey === loadKey) {
                    this.resetTranslatedInheritanceState();
                }
            }
        },

        getInheritedCustomField(customFieldName) {
            const value = this.getInheritedCustomFields(customFieldName);

            if (value !== null && value !== undefined) {
                return value;
            }

            return this.getDefaultInheritedCustomFieldValue(customFieldName);
        },

        getCustomFieldInformation(customFieldName) {
            let returnValue;

            this.sets.some((set) =>
                set.customFields.some((customField) => {
                    const isMatching = customField.name === customFieldName;

                    if (isMatching) {
                        returnValue = customField;
                    }

                    return isMatching;
                }),
            );

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

            return this.componentsWithMapInheritanceSupport.includes(componentName);
        },

        isMeteorComponent(customField) {
            return [
                'bool',
                'text',
                'number',
                'float',
                'int',
                'datetime',
            ].includes(customField.type);
        },

        getBind(customField, props) {
            const customFieldClone = Shopware.Utils.object.cloneDeep(customField);

            const isMeteorComponent = this.isMeteorComponent(customField);
            const inheritedCustomFieldValue = props.isInheritField ? this.getInheritedCustomField(customField.name) : null;

            if (customFieldClone.type === 'bool') {
                customFieldClone.config.bordered = true;
            }

            if (this.supportsMapInheritance(customFieldClone)) {
                customFieldClone.mapInheritance = props;

                // Special case for meteor components
                if (isMeteorComponent) {
                    Object.assign(customFieldClone, mapInheritanceSlotPropsToMeteorProps(props, inheritedCustomFieldValue));
                    customFieldClone.disabled = this.disabled || props.isInherited;
                }

                return customFieldClone;
            }

            if (customFieldClone.config.customFieldType === 'entity' && customFieldClone.config.entity === 'product') {
                const criteria = new Criteria(1, 25).setTotalCountMode(0);
                criteria.addAssociation('options.group');

                customFieldClone.config.criteria = criteria;
                customFieldClone.config.displayVariants = true;
            }

            delete customFieldClone.config.label;
            delete customFieldClone.config.helpText;

            return customFieldClone;
        },

        getElementEventListeners(customField, props) {
            const isMeteorComponent = this.isMeteorComponent(customField);
            const eventHandler = {};

            if (isMeteorComponent) {
                eventHandler['inheritance-remove'] = props.removeInheritance;
                eventHandler['inheritance-restore'] = props.restoreInheritance;
            }

            return eventHandler;
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

            criteria.getAssociation('customFields').addSorting(Criteria.naturalSorting('config.customFieldPosition'));

            return criteria;
        },

        loadCustomFieldSet(setId) {
            if (this.loadingFields.includes(setId)) {
                // as we might triggered multiple times with the same item, we store the loading set in a heap cache
                return;
            }

            // failsave dealing with sets (should be an entityCollection, but in reality might be just an array)
            const set = this.sets.get ? this.sets.get(setId) : this.sets.find((s) => s.id === setId);

            if (set.customFields && set.customFields.length > 0) {
                // already loaded, so do nothing
                return;
            }

            // indicate the loading of this item
            this.loadingFields.push(setId);

            // fully load the set
            this.customFieldSetRepository
                .get(setId, Shopware.Context.api, this.customFieldSetCriteriaById())
                .then((newSet) => {
                    // replace the fully fetched set
                    this.sets.forEach((originalSet, index) => {
                        if (originalSet.id === newSet.id) {
                            this.sets[index] = newSet;
                        }
                    });

                    // remove the set from the currently loading onces and refresh the visible sets
                    this.loadingFields = this.loadingFields.filter((s) => s.id !== setId);
                })
                .catch((error) => {
                    console.error(error);
                    // in case of error make loading again possible
                    this.loadingFields = this.loadingFields.filter((s) => s.id !== setId);
                });
        },

        resetTabs() {
            if (this.visibleCustomFieldSets.length > 0 && this.$refs.tabComponent) {
                // Reset state of tab component if custom field selection changes
                this.$refs.tabComponent.mountedComponent();
                this.$refs.tabComponent.setActiveItem({
                    name: this.visibleCustomFieldSets[0].id,
                });
            }
        },

        waitForTabComponent() {
            if (this.$refs.tabComponent || this.tabWaitsAttempts > this.tabWaitMaxAttempts) {
                return this.resetTabs();
            }
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
};
