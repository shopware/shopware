/**
 * @sw-package inventory
 */
import template from './sw-settings-measurement.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            measurementUnits: {
                system: null,
                length: null,
                weight: null,
            },
            defaultDisplayUnits: [],
            measurementSystems: [],
            measurementSystem: null,
            isLoading: false,
        };
    },

    computed: {
        measurementSystemRepository() {
            return this.repositoryFactory.create('measurement_system');
        },

        measurementSystemCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addAssociation('units');

            return criteria;
        },

        defaultLengthUnit() {
            const units = this.measurementSystem?.units || [];

            const lengthUnit = this.defaultDisplayUnits.find((u) => u.type === 'length');
            const defaultLengthUnit = units.find((unit) => unit.type === 'length' && unit.default);

            return units.find((unit) => unit.shortName === lengthUnit.shortName) || defaultLengthUnit;
        },

        defaultWeightUnit() {
            const units = this.measurementSystem?.units || [];

            const weightUnit = this.defaultDisplayUnits.find((u) => u.type === 'weight');
            const defaultWeightUnit = units.find((unit) => unit.type === 'weight' && unit.default);

            return units.find((unit) => unit.shortName === weightUnit.shortName) || defaultWeightUnit;
        },

        requiredFields() {
            const isEmptyArray = (arr) => {
                return Array.isArray(arr) && arr.length === 0;
            };

            return {
                system: isEmptyArray(this.measurementUnits.system) || !this.measurementUnits.system,
                length: isEmptyArray(this.measurementUnits.length) || !this.measurementUnits.length,
                weight: isEmptyArray(this.measurementUnits.weight) || !this.measurementUnits.weight,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const measurementUnits = await this.getMeasurementUnits();
            this.measurementUnits = {
                system: measurementUnits['core.measurementUnits.system'],
                length: measurementUnits['core.measurementUnits.length'],
                weight: measurementUnits['core.measurementUnits.weight'],
            };

            this.measurementSystems = await this.getDefaultMeasurementSystems();
            this.measurementSystem = this.measurementSystems.find(
                (system) => system.technicalName === this.measurementUnits.system,
            );

            this.defaultDisplayUnits = (this.measurementSystem?.units || []).filter((u) =>
                [
                    this.measurementUnits.length,
                    this.measurementUnits.weight,
                ].includes(u.shortName),
            );
        },

        getMeasurementUnits() {
            return this.systemConfigApiService.getValues('core.measurementUnits');
        },

        getDefaultMeasurementSystems() {
            return this.measurementSystemRepository.search(this.measurementSystemCriteria);
        },

        async onSave() {
            this.isLoading = true;

            const invalidFields = Object.keys(this.requiredFields).filter((field) => this.requiredFields[field]);

            try {
                if (invalidFields.length > 0) {
                    invalidFields.forEach((property) => {
                        const expression = `measurement_system.${this.measurementSystem.id}.${property}`;
                        const error = new ShopwareError({
                            code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                            detail: 'This field must not be empty.',
                            selfLink: expression,
                        });

                        Shopware.Store.get('error').addApiError({ expression, error });
                    });
                    this.isLoading = false;

                    return;
                }

                await this.systemConfigApiService.saveValues({
                    'core.measurementUnits.system': this.measurementUnits.system,
                    'core.measurementUnits.length': this.measurementUnits.length,
                    'core.measurementUnits.weight': this.measurementUnits.weight,
                });

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-measurement.notification.saveMeasurementSuccess'),
                });

                Shopware.Store.get('error').resetApiErrors();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message || this.$t('sw-settings-measurement.notification.saveMeasurementError'),
                });
            } finally {
                this.defaultDisplayUnits = (this.measurementSystem?.units || []).filter((u) =>
                    [
                        this.measurementUnits.length,
                        this.measurementUnits.weight,
                    ].includes(u.shortName),
                );

                this.isLoading = false;
            }
        },

        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);
        },

        async onChangeMeasurementSystem(technicalName) {
            if ((Array.isArray(technicalName) && technicalName.length === 0) || !technicalName) {
                return;
            }

            Shopware.Store.get('error').resetApiErrors();

            this.measurementSystem = this.measurementSystems.find((system) => system.technicalName === technicalName);

            this.measurementUnits = {
                system: technicalName,
                length: this.defaultLengthUnit.shortName,
                weight: this.defaultWeightUnit.shortName,
            };
        },
    },
};
