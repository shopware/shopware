import { Component, State, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-salutation-detail.html.twig';

Component.register('sw-settings-salutation-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('salutation')
    ],

    data() {
        return {
            entityName: 'salutation',
            isLoading: false,
            salutation: null,
            invalidKey: false,
            isKeyChecking: false,
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
            return this.placeholder(this.salutation, 'displayName');
        },

        salutationStore() {
            return State.getStore('salutation');
        },

        entityDescription() {
            return this.placeholder(
                this.salutation,
                'salutationKey',
                this.$tc('sw-settings-salutation.detail.placeholderNewSalutation')
            );
        },

        invalidKeyErrorMessage() {
            if (this.invalidKey && !this.isKeyChecking) {
                return this.$tc('sw-settings-salutation.detail.invalidKeyMessage');
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salutation = this.salutationStore.getById(this.$route.params.id);
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            return this.salutation.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-salutation.general.titleError'),
                    message: this.$tc('sw-settings-salutation.detail.messageSaveError')
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.salutation.discardChanges();
            this.$router.push({ name: 'sw.settings.salutation.index' });
        },

        onChange() {
            this.isKeyChecking = true;
            this.onChangeDebounce();
        },

        onChangeDebounce: utils.debounce(function executeChange() {
            if (this.salutation.salutationKey === null ||
                this.salutation.salutationKey.trim() === '' ||
                this.salutation.salutationKey.trim() === this.salutation.salutationKey
            ) {
                this.invalidKey = false;
                this.isKeyChecking = false;
                return;
            }

            const criteria = CriteriaFactory.equals('salutationKey', this.salutation.salutationKey);
            this.salutationStore.getList({ page: 1, limit: 1, criteria }).then((response) => {
                this.invalidKey = !!response.total;
            }).catch(() => {
                this.invalidKey = true;
            }).finally(() => {
                this.isKeyChecking = false;
            });
        }, 500)
    }
});
