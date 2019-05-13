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
            promotion: {},
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.promotion, 'name');
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

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.$emit('save');
            const promotionName = this.promotion.name;
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: promotionName }
            );
            this.isSaveSuccessful = false;
            this.isLoading = true;


            return this.promotion.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                let customMessage = `${messageSaveError} <br />`;
                this.promotion.errors.forEach((promotionError) => {
                    customMessage += `${promotionError.detail} <br />`;
                });
                this.promotion.errors = [];
                this.createNotificationError({
                    title: titleSaveError,
                    message: customMessage
                });
                warn(this._name, exception.message, exception.response);
                this.isLoading = false;
                throw exception;
            });
        }
    }
});
