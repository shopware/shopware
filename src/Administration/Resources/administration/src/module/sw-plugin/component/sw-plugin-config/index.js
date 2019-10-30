import template from './sw-plugin-config.html.twig';

const { Mixin } = Shopware;

Shopware.Component.register('sw-plugin-config', {
    mixins: [
        Mixin.getByName('notification')
    ],

    template,

    data() {
        const domain = `${this.$route.params.namespace}.config`;
        return {
            namespace: this.$route.params.namespace,
            domain: domain,
            salesChannelId: null,
            config: {},
            actualConfigData: {}
        };
    },

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-plugin-config.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
