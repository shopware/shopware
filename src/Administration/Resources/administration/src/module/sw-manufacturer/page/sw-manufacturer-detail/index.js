import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.less';

Component.register('sw-manufacturer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            manufacturerId: null,
            manufacturer: {}
        };
    },

    computed: {
        manufacturerStore() {
            return State.getStore('product_manufacturer');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.manufacturerId = this.$route.params.id;
                this.manufacturer = this.manufacturerStore.getById(this.manufacturerId);
            }
        },

        onSave() {
            const manufacturerName = this.manufacturer.name;
            const titleSaveSuccess = this.$tc('sw-manufacturer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-manufacturer.detail.messageSaveSuccess', 0, { name: manufacturerName });

            this.manufacturer.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
