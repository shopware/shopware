import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-store.html.twig';

Component.register('sw-settings-store', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('settings')
    ],

    data() {
        return {
            settings: {}
        };
    },

    computed: {
        storeSettingsStore() {
            return State.getStore('store_settings');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.storeSettingsStore.getList().then((response) => {
                this.settings = response.items.filter(setting => setting.key === 'host');
                if (this.settings.length < 1) {
                    this.settings = this.storeSettingsStore.create();
                    this.settings.key = 'host';
                } else {
                    this.settings = this.settings[0];
                }
            });
        },

        onClickSave() {
            return this.settings.save().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-store.general.titleSave'),
                    message: this.$tc('sw-settings-store.general.messageSave')
                });
            });
        }
    }
});
