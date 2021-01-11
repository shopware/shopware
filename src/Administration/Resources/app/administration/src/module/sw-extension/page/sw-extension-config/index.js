import template from './sw-extension-config.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-extension-config', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        namespace: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            salesChannelId: null
        };
    },

    computed: {
        domain() {
            return `${this.namespace}.config`;
        }
    },

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    // eslint-disable-next-line max-len
                    message: this.$tc('sw-extension-store.component.sw-extension-config.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    message: err
                });
            });
        }
    }
});
