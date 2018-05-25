import { Component, Mixin } from 'src/core/shopware';
import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.less';

Component.register('sw-manufacturer-detail', {
    template,

    mixins: [
        Mixin.getByName('manufacturer'),
        Mixin.getByName('notification')
    ],

    created() {
        if (this.$route.params.id) {
            this.manufacturerId = this.$route.params.id;
        }
    },

    methods: {
        onSave() {
            const manufacturerName = this.manufacturer.name;
            const titleSaveSuccess = this.$tc('sw-manufacturer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-manufacturer.detail.messageSaveSuccess', 0, { name: manufacturerName });

            this.saveManufacturer().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
