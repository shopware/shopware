import template from './sw-settings-payment-detail.html.twig';
import './sw-settings-payment-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

Component.register('sw-settings-payment-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    inject: ['repositoryFactory'],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            paymentMethod: null,
            mediaItem: null,
            uploadTag: 'sw-payment-method-upload-tag',
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    watch: {
        'paymentMethod.mediaId'() {
            if (this.paymentMethod.mediaId) {
                this.setMediaItem({ targetId: this.paymentMethod.mediaId });
            }
        }
    },

    computed: {
        identifier() {
            return this.placeholder(this.paymentMethod, 'name');
        },

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
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
                    Criteria.contains('rule.moduleTypes.types', 'payment'),
                    Criteria.equals('rule.moduleTypes', null)
                ]
            ));

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.paymentMethodId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        onSaveRule(ruleId) {
            this.paymentMethod.availabilityRuleId = ruleId;
        },

        onDismissRule() {
            this.paymentMethod.availabilityRuleId = null;
        },

        abortOnLanguageChange() {
            return this.paymentMethodRepository.hasChanges(this.paymentMethod);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        loadEntityData() {
            this.isLoading = true;

            this.paymentMethodRepository.get(this.paymentMethodId, Shopware.Context.api)
                .then((paymentMethod) => {
                    this.paymentMethod = paymentMethod;
                    this.setMediaItem({ targetId: this.paymentMethod.mediaId });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'
            );
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.paymentMethodRepository.save(this.paymentMethod, Shopware.Context.api)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.$refs.mediaSidebarItem.getList();
                })
                .catch((exception) => {
                    this.createNotificationError({
                        title: titleSaveError,
                        message: messageSaveError
                    });
                    warn(this._name, exception.message, exception.response);
                    throw exception;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.payment.index' });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId, Shopware.Context.api)
                .then((updatedMedia) => {
                    this.mediaItem = updatedMedia;
                    this.paymentMethod.mediaId = targetId;
                });
        },

        setMediaFromSidebar(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.paymentMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.paymentMethod.mediaId = null;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        }
    }
});
