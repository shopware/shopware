/**
 * @sw-package inventory
 */
import template from './sw-settings-measurement.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

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

        onChangeMeasurementSystem() {
            this.measurementSystem.lengthUnitId = this.defaultLengthUnit.id;
            this.measurementSystem.massUnitId = this.defaultMassUnit.id;
        },
    },
};
