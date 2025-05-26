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
            measurementUnits: {
                system: null,
                length: null,
                weight: null,
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
            criteria.addFilter(Criteria.equals('measurementSystem.technicalName', this.measurementSystem.typeId));

            return criteria;
        },

        weightUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('type', 'weight'));
            criteria.addFilter(Criteria.equals('measurementSystem.technicalName', this.measurementSystem.system));

            return criteria;
        },

        defaultUnitCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('default', true));
            criteria.addFilter(Criteria.equals('measurementSystem.technicalName', this.measurementSystem.system));

            return criteria;
        },

        defaultLengthUnit() {
            return this.defaultDisplayUnits.find((u) => {
                return u.type === 'length';
            });
        },

        defaultWeightUnit() {
            return this.defaultDisplayUnits.find((u) => {
                return u.type === 'weight';
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
                system: measurementSystem['core.measurementUnits.system'],
                length: measurementSystem['core.measurementUnits.length'],
                weight: measurementSystem['core.measurementUnits.weight'],
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

        onChangeMeasurementSystem() {
            this.measurementUnits.length = this.defaultLengthUnit.shortName;
            this.measurementUnits.weight = this.defaultWeightUnit.shortName;
        },
    },
};
