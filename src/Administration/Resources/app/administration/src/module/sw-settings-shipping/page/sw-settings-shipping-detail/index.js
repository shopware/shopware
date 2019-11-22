import template from './sw-settings-shipping-detail.html.twig';
import './sw-settings-shipping-detail.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

Component.register('sw-settings-shipping-detail', {
    template,

    inject: ['ruleConditionDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('shippingMethod')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    watch: {
        'shippingMethod.mediaId'() {
            if (this.shippingMethod.mediaId) {
                this.setMediaItem({ targetId: this.shippingMethod.mediaId });
            }
        }
    },

    data() {
        return {
            shippingMethod: {},
            logoMediaItem: null,
            uploadTag: 'sw-shipping-method-upload-tag',
            isSaveSuccessful: false,
            isProcessLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.shippingMethod, 'name');
        },

        shippingMethodStore() {
            return StateDeprecated.getStore('shipping_method');
        },

        ruleStore() {
            return StateDeprecated.getStore('rule');
        },

        priceRuleStore() {
            return StateDeprecated.getStore('shipping_method_price');
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        deliveryTimeStore() {
            return StateDeprecated.getStore('delivery_time');
        },

        isLoading() {
            return Object.keys(this.shippingMethod).length === 0
                || this.shippingMethod.isLoading;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        ruleFilter() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'shipping'),
                    Criteria.equals('rule.moduleTypes', null)
                ]
            ));

            return criteria;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.shippingMethodId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        onSaveRule(ruleId) {
            this.shippingMethod.availabilityRuleId = ruleId;
            this.$refs.priceMatrices.$emit('rule-add');
        },

        loadEntityData() {
            this.shippingMethod = this.shippingMethodStore.getById(this.shippingMethodId);
        },

        abortOnLanguageChange() {
            return this.shippingMethod.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const shippingMethodName = this.shippingMethod.name || this.placeholder(this.shippingMethod, 'name');
            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: shippingMethodName }
            );
            this.isSaveSuccessful = false;
            this.isProcessLoading = true;

            return this.shippingMethod.save().then(() => {
                this.isProcessLoading = false;
                this.isSaveSuccessful = true;
                this.$refs.mediaSidebarItem.getList();
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                this.isProcessLoading = false;
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.shipping.index' });
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.logoMediaItem = updatedMedia;
            });
            this.shippingMethod.mediaId = targetId;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        setMediaFromSidebar(mediaEntity) {
            this.logoMediaItem = mediaEntity;
            this.shippingMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.logoMediaItem = null;
            this.shippingMethod.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        }
    }
});
