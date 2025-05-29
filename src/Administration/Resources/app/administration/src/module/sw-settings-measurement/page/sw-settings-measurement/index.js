/**
 * @sw-package inventory
 */
import template from './sw-settings-measurement.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
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
            return this.defaultDisplayUnits.find((u) => u.type === 'length');
        },

        defaultWeightUnit() {
            return this.defaultDisplayUnits.find((u) => u.type === 'weight');
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

            this.measurementSystem = await this.getDefaultMeasurementSystem();

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

        async getDefaultMeasurementSystem() {
            const criteria = cloneDeep(this.measurementSystemCriteria);
            criteria.setLimit(1);

            if (this.measurementUnits.system) {
                criteria.addFilter(Criteria.equals('technicalName', this.measurementUnits.system));
            }

            const measurement = await this.measurementSystemRepository.search(criteria);

            return measurement.first();
        },

        async onSave() {
            this.isLoading = true;
            try {
                await this.systemConfigApiService.saveValues({
                    'core.measurementUnits.system': this.measurementUnits.system,
                    'core.measurementUnits.length': this.measurementUnits.length,
                    'core.measurementUnits.weight': this.measurementUnits.weight,
                });
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-measurement.notification.saveMeasurementSuccess'),
                });
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message || this.$t('sw-settings-measurement.notification.saveMeasurementError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);
        },

        async onChangeMeasurementSystem(measurementSystem) {
            if (!measurementSystem) {
                return;
            }

            this.measurementUnits.system = measurementSystem.technicalName;
            const units = measurementSystem.units;

            this.measurementSystem = measurementSystem;

            const defaultLengthUnit =
                units.find((unit) => unit.shortName === this.defaultLengthUnit.shortName) ||
                units.find((unit) => unit.type === 'length' && unit.default);

            if (defaultLengthUnit) {
                this.measurementUnits.length = defaultLengthUnit.shortName;
            }

            const defaultWeightUnit =
                units.find((unit) => unit.shortName === this.defaultWeightUnit.shortName) ||
                units.find((unit) => unit.type === 'weight' && unit.default);

            if (defaultWeightUnit) {
                this.measurementUnits.weight = defaultWeightUnit.shortName;
            }
        },
    },
};
