/**
 * @package system-settings
 */
import template from './sw-custom-field-type-entity.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],
    mounted() {
        this.customEntityRepository.search(new Criteria(), Shopware.Context.api)
            .then(result => {
                this.customEntities = result;
            });
    },
    data() {
        return {
            customEntities: [],
        };
    },
    computed: {
        entityTypes() {
            const entityTypes = [
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.product'),
                    value: 'product',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.category'),
                    value: 'category',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.shippingMethod'),
                    value: 'shipping_method',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.paymentMethod'),
                    value: 'payment_method',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.country'),
                    value: 'country',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.customer'),
                    value: 'customer',
                    config: {
                        labelProperty: ['firstName', 'lastName'],
                    },
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.salesChannel'),
                    value: 'sales_channel',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.manufacturer'),
                    value: 'product_manufacturer',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.dynamicProductGroup'),
                    value: 'product_stream',
                },
                {
                    label: this.$tc('sw-settings-custom-field.customField.entity.shoppingExperienceLayout'),
                    value: 'cms_page',
                },
            ];

            this.customFieldsAwareCustomEntities.forEach(customEntity => {
                entityTypes.push({
                    label: this.$tc(`${customEntity.name}.label`),
                    value: customEntity.name,
                    config: {
                        labelProperty: customEntity.labelProperty,
                    },
                });
            });

            return entityTypes;
        },

        customFieldsAwareCustomEntities() {
            return this.customEntities.filter(customEntity => customEntity.customFieldsAware);
        },

        customEntityRepository() {
            return this.repositoryFactory.create(
                'custom_entity',
            );
        },

        sortedEntityTypes() {
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            return this.entityTypes.sort((a, b) => {
                return a.label.localeCompare(b.label);
            });
        },
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.hasOwnProperty('componentName')) {
                this.currentCustomField.config.componentName = 'sw-entity-single-select';
            }

            this.multiSelectSwitch = this.currentCustomField.config.componentName === 'sw-entity-multi-id-select';
        },

        onChangeEntityType(entity) {
            const entityType = this.entityTypes.find(type => type.value === entity);

            this.$delete(this.currentCustomField.config, 'labelProperty');

            // pass the label property into the custom field's config to allow different / multiple labelProperties
            if (entityType.hasOwnProperty('config') && entityType.config.hasOwnProperty('labelProperty')) {
                this.currentCustomField.config.labelProperty = entityType.config.labelProperty;
            }
        },

        onChangeMultiSelectSwitch(state) {
            if (state) {
                this.currentCustomField.config.componentName = 'sw-entity-multi-id-select';
                return;
            }

            this.currentCustomField.config.componentName = 'sw-entity-single-select';
        },
    },
};
