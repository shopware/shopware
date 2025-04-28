/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
        'repositoryFactory',
        'systemConfigApiService',
        'userConfigService',
    ],

    data() {
        return {
            showMediaModal: false,
            measurementSystemConfig: null,
            defaultUnits: {
                length: 'mm',
                height: 'mm',
                width: 'mm',
                weight: 'kg',
            },
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        loading() {
            return Shopware.Store.get('swProductDetail').loading;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        customFieldSets() {
            return Shopware.Store.get('swProductDetail').customFieldSets;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        productStates() {
            return Shopware.Store.get('swProductDetail').productStates;
        },

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldsExists;
        },

        measurementDisplayUnitRepository() {
            return this.repositoryFactory.create('measurement_display_unit');
        },

        measurementDisplayUnitCriteria() {
            const criteria = new Criteria();

            const measurementSystemDisplayIds = [
                this.measurementSystemConfig?.['core.measurementSystem.massUnitId'],
                this.measurementSystemConfig?.['core.measurementSystem.lengthUnitId'],
            ].filter(id => id != null);

            if (measurementSystemDisplayIds.length) {
                criteria.setIds(measurementSystemDisplayIds);
            }

            return criteria;
        },
    },

    created() {
        this.initMeasurementSystem();
    },

    methods: {
        showProductCard(key) {
            return Shopware.Store.get('swProductDetail').showProductCard(key);
        },

        async initMeasurementSystem() {
            const productMeasurementDefaultUnits = await this.userConfigService.search(['product.measurement.units']);
            if (productMeasurementDefaultUnits.data?.['product.measurement.units']) {
                this.defaultUnits = productMeasurementDefaultUnits.data['product.measurement.units'];
                return;
            }

            this.measurementSystemConfig = await this.getMeasurementSystemConfig();
            if (!this.measurementSystemConfig) {
                return;
            }

            await this.setMeasurementSystemDefaultUnits();
        },

        async setMeasurementSystemDefaultUnits() {
            const measurementSystemDefaultUnits = await this.measurementDisplayUnitRepository.search(
                this.measurementDisplayUnitCriteria,
            );

            if (!measurementSystemDefaultUnits) {
                return;
            }

            const mass = measurementSystemDefaultUnits.find(item => item.type === 'mass')?.shortName
                ?? this.defaultUnits.weight;

            const length = measurementSystemDefaultUnits.find(item => item.type === 'length')?.shortName
                ?? this.defaultUnits.length;

            this.defaultUnits = {
                length: length,
                width: length,
                height: length,
                weight: mass,
            };
        },

        getMeasurementSystemConfig() {
            return this.systemConfigApiService.getValues('core.measurementSystem');
        },
    },
};
