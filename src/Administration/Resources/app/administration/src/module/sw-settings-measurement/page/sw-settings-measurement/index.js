import template from './sw-settings-measurement.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @sw-package inventory
 * @private
 * @component
 * @description
 * This component handles the measurement system settings in the administration.
 * It allows users to configure the measurement system type, dimension unit, and weight unit.
 */
export default {
    template,

    inject: [
        'acl',
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
            measurementSystemConfig: {
                id: null,
                type: null,
                lengthUnit: null,
                massUnit: null,
            },
            measurementSystemOptions: {
                type: [],
                lengthUnit: [],
                massUnit: [],
            },
            measurementSystems: [],
            measurementDisplayUnits: [],
        };
    },

    computed: {
        measurementSystemRepository() {
            return this.repositoryFactory.create('measurement_system');
        },

        measurementDisplayUnitRepository() {
            return this.repositoryFactory.create('measurement_display_unit');
        },

        measurementSystemCriteria() {
            const criteria = new Criteria(1, null);

            return criteria;
        },

        measurementDisplayUnitCriteria() {
            const criteria = new Criteria(1, null);

            return criteria;
        },

        currentMeasurementSystemConfig() {
            return this.measurementSystems.find((measurementSystem) => {
                return measurementSystem.technicalName === this.measurementSystemConfig.type;
            });
        },

        currentLengthUnitOptions() {
            return this.measurementDisplayUnits.filter((measurementDisplayUnit) => {
                const { measurementSystemId, type } = measurementDisplayUnit;

                return measurementSystemId === this.measurementSystemConfig.id && type === 'length';
            });
        },

        currentMassUnitOptions() {
            return this.measurementDisplayUnits.filter((measurementDisplayUnit) => {
                const { measurementSystemId, type } = measurementDisplayUnit;

                return measurementSystemId === this.measurementSystemConfig.id && type === 'weight';
            });
        },

        currentDefaultLengthUnit() {
            return this.measurementDisplayUnits.find((measurementDisplayUnit) => {
                const { measurementSystemId, type, default: isDefault } = measurementDisplayUnit;

                return measurementSystemId === this.measurementSystemConfig.id && type === 'length' && isDefault;
            });
        },

        currentDefaultMassUnit() {
            return this.measurementDisplayUnits.find((measurementDisplayUnit) => {
                const { measurementSystemId, type, default: isDefault } = measurementDisplayUnit;

                return measurementSystemId === this.measurementSystemConfig.id && type === 'weight' && isDefault;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.getMeasurementSystemConfig();
            await this.getMeasurementSystems();
            this.measurementSystemConfig.id = this.currentMeasurementSystemConfig.id;

            await this.getMeasurementDisplayUnits();

            this.measurementSystemOptions.type = this.measurementSystems;
            this.measurementSystemOptions.lengthUnit = this.currentLengthUnitOptions;
            this.measurementSystemOptions.massUnit = this.currentMassUnitOptions;

        },

        async getMeasurementSystemConfig() {
            const response = await this.systemConfigApiService.getValues('core.measurementSystem');
            this.measurementSystemConfig = {
                type: response['core.measurementSystem.type'],
                lengthUnit: response['core.measurementSystem.lengthUnit'],
                massUnit: response['core.measurementSystem.massUnit'],
            };
        },

        async getMeasurementSystems() {
            const response = await this.measurementSystemRepository.search(this.measurementSystemCriteria);
            this.measurementSystems = response;
        },

        async getMeasurementDisplayUnits() {
            const response = await this.measurementDisplayUnitRepository.search(this.measurementDisplayUnitCriteria);
            this.measurementDisplayUnits = response;
        },

        onMeasurementSystemConfigTypeChange() {
            this.measurementSystemConfig.id = this.currentMeasurementSystemConfig.id;

            this.measurementSystemConfig.lengthUnit = this.currentDefaultLengthUnit.shortName;
            this.measurementSystemConfig.massUnit = this.currentDefaultMassUnit.shortName;

            this.measurementSystemOptions.lengthUnit = this.currentLengthUnitOptions;
            this.measurementSystemOptions.massUnit = this.currentMassUnitOptions;
        },

        async onSave() {
            this.isLoading = true;
            try {
                await this.systemConfigApiService.saveValues({
                    'core.measurementSystem.type': this.measurementSystemConfig.type,
                    'core.measurementSystem.lengthUnit': this.measurementSystemConfig.lengthUnit,
                    'core.measurementSystem.massUnit': this.measurementSystemConfig.massUnit,
                });

                this.createNotificationSuccess({
                    title: 'Success',
                    message: 'Measurement system settings saved successfully',
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Error',
                    message: 'Failed to save measurement system settings',
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
