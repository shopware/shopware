import template from './sw-settings-delivery-time-detail.html.twig';
import {
    aclService,
    customFieldDataProviderService,
    getApiContext,
    mapPropertyErrors,
    notificationMixin,
    repositoryFactoryService,
} from '../dependencies';

/**
 * @sw-package discovery
 */

const ShopwareError = Shopware.Classes.ShopwareError;

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService, customFieldDataProviderService],

    mixins: [notificationMixin],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data() {
        return {
            deliveryTime: null,
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapPropertyErrors('deliveryTime', [
            'name',
            'min',
            'max',
            'unit',
        ]),

        deliveryTimeRepository() {
            return this.repositoryFactory.create('delivery_time');
        },

        deliveryTimeUnits() {
            return [
                {
                    value: 'hour',
                    label: this.$t('sw-settings-delivery-time.detail.selectionUnitHour'),
                },
                {
                    value: 'day',
                    label: this.$t('sw-settings-delivery-time.detail.selectionUnitDay'),
                },
                {
                    value: 'week',
                    label: this.$t('sw-settings-delivery-time.detail.selectionUnitWeek'),
                },
                {
                    value: 'month',
                    label: this.$t('sw-settings-delivery-time.detail.selectionUnitMonth'),
                },
                {
                    value: 'year',
                    label: this.$t('sw-settings-delivery-time.detail.selectionUnitYear'),
                },
            ];
        },

        displayName() {
            if (this.deliveryTime && this.deliveryTime.name) {
                return this.deliveryTime.name;
            }
            return this.$t('sw-settings-delivery-time.detail.textHeadlineNew');
        },

        isInvalidMinField() {
            return this.deliveryTime.min > this.deliveryTime.max;
        },

        invalidMinError() {
            if (this.isInvalidMinField) {
                return new ShopwareError({ code: 'DELIVERY_TIME_MIN_INVALID' });
            }
            return null;
        },

        allowSave() {
            if (!this.deliveryTime) {
                return false;
            }

            if (this.deliveryTime.isNew()) {
                return this.acl.can('delivery_times.creator');
            }

            return this.acl.can('delivery_times.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        showCustomFields() {
            return this.deliveryTime && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.loadCustomFieldSets();

            this.deliveryTimeRepository
                .get(this.$route.params.id.toLowerCase())
                .then((deliveryTime) => {
                    this.deliveryTime = deliveryTime;
                    this.isLoading = false;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-delivery-time.detail.errorLoad'),
                    });

                    this.isLoading = false;
                    throw exception;
                });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('delivery_time').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            return this.deliveryTimeRepository
                .save(this.deliveryTime, getApiContext())
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-delivery-time.detail.errorSave'),
                    });

                    this.isLoading = false;
                    throw exception;
                });
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.delivery.time.index' });
        },
    },
};
