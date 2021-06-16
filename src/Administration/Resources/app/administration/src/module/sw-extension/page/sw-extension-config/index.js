import template from './sw-extension-config.html.twig';
import './sw-extension-config.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-config', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        namespace: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            salesChannelId: null,
        };
    },

    computed: {
        domain() {
            return `${this.namespace}.config`;
        },
    },

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-extension-store.component.sw-extension-config.messageSaveSuccess'),
                });
            }).catch((err) => {
                this.createNotificationError({
                    message: err,
                });
            });
        },
    },
});
