import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-promotion-detail.html.twig';

Component.register('sw-promotion-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('promotion')
    ],

    data() {
        return {
            promotion: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            // ToDo: If 'name' is translatable, please update:
            // ToDo: return this.placeholder(this.promotion, 'name');
            return this.promotion.name;
        },
        promotionStore() {
            return State.getStore('promotion');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.promotionId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.promotion = this.promotionStore.getById(this.promotionId);
        },

        abortOnLanguageChange() {
            return this.promotion.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            const promotionName = this.promotion.name;
            const titleSaveSuccess = this.$tc('sw-promotion.detail.header.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-promotion.detail.header.messageSaveSuccess', 0, { name: promotionName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: promotionName }
            );

            return this.promotion.save().then(() => {
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
        }
    }
});
