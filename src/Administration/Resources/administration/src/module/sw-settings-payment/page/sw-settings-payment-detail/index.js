import { Component, State, Mixin } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-payment-detail.html.twig';
import './sw-settings-payment-detail.scss';

Component.register('sw-settings-payment-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('paymentMethod')
    ],

    data() {
        return {
            paymentMethod: {},
            mediaItem: null,
            uploadTag: 'sw-payment-method-upload-tag',
            ruleFilter: CriteriaFactory.multi(
                'OR',
                CriteriaFactory.contains('rule.moduleTypes.types', 'payment'),
                CriteriaFactory.equals('rule.moduleTypes', null)
            )
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

        paymentMethodStore() {
            return State.getStore('payment_method');
        },

        ruleStore() {
            return State.getStore('rule');
        },

        mediaStore() {
            return State.getStore('media');
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

        abortOnLanguageChange() {
            return this.paymentMethod.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        loadEntityData() {
            this.paymentMethod = this.paymentMethodStore.getById(this.paymentMethodId);
            if (this.paymentMethod.mediaId) {
                this.setMediaItem({ targetId: this.paymentMethod.mediaId });
            }
        },

        onSave() {
            const paymentMethodName = this.paymentMethod.name;
            const titleSaveSuccess = this.$tc('sw-settings-payment.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-payment.detail.messageSaveSuccess', 0, {
                name: paymentMethodName
            });

            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: paymentMethodName }
            );

            return this.paymentMethod.save().then(() => {
                this.$refs.mediaSidebarItem.getList();

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            });
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.mediaItem = updatedMedia;
            });
            this.paymentMethod.mediaId = targetId;
        },

        setMediaFromSidebar(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.paymentMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.paymentMethod.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        }
    }
});
