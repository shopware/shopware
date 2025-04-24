import template from './sw-settings-measurement.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @sw-package inventory
 * @private
 * @description This component handles the measurement system settings in the administration.
 * It allows users to configure the measurement system type, length unit, and mass unit.
 */
export default {
    template,

    inject: [
        'acl',
        'systemConfigApiService',
        'repositoryFactory',
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
            measurementSystem: {
                typeId: null,
                lengthUnitId: null,
                massUnitId: null,
            },
            defaultDisplayUnits: [],
            isLoading: false,
        };
    },

    computed: {
        displayUnitRepository() {
            return this.repositoryFactory.create('measurement_display_unit');
        },

        lengthUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'length'));
            criteria.addFilter(Criteria.equals('measurementSystemId', this.measurementSystem.typeId));

            return criteria;
        },

        massUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'mass'));
            criteria.addFilter(Criteria.equals('measurementSystemId', this.measurementSystem.typeId));

            return criteria;
        },

        defaultUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('default', true));

            return criteria;
        },

        defaultLengthUnit() {
            return this.defaultDisplayUnits.find((u) => {
                return u.type === 'length' && u.measurementSystemId === this.measurementSystem.typeId;
            });
        },

        defaultMassUnit() {
            return this.defaultDisplayUnits.find((u) => {
                return u.type === 'mass' && u.measurementSystemId === this.measurementSystem.typeId;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const [measurementSystem, defaultDisplayUnits] = await Promise.all([
                this.getMeasurementSystem(),
                this.getDefaultDisplayUnits(),
            ]);

            this.measurementSystem = {
                typeId: measurementSystem['core.measurementSystem.typeId'],
                lengthUnitId: measurementSystem['core.measurementSystem.lengthUnitId'],
                massUnitId: measurementSystem['core.measurementSystem.massUnitId'],
            };
            this.defaultDisplayUnits = defaultDisplayUnits;
        },

        getMeasurementSystem() {
            return this.systemConfigApiService.getValues('core.measurementSystem');
        },

        getDefaultDisplayUnits() {
            return this.displayUnitRepository.search(this.defaultUnitCriteria);
        },

        async onSave() {
            this.isLoading = true;
            try {
                await this.systemConfigApiService.saveValues({
                    'core.measurementSystem.typeId': this.measurementSystem.typeId,
                    'core.measurementSystem.lengthUnitId': this.measurementSystem.lengthUnitId,
                    'core.measurementSystem.massUnitId': this.measurementSystem.massUnitId,
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

        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);
        },

        onChangeMeasurementSystem() {
            this.measurementSystem.lengthUnitId = this.defaultLengthUnit.id;
            this.measurementSystem.massUnitId = this.defaultMassUnit.id;
        },
    },
};
