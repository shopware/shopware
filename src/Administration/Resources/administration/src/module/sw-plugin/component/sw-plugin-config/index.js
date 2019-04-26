import { Mixin } from 'src/core/shopware';
import template from './sw-plugin-config.html.twig';

export default {
    name: 'sw-plugin-config',

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: ['systemConfigApiService'],

    template,

    data() {
        const domain = this.systemConfigApiService.getDomainFromNamespace(this.$route.params.namespace);
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
};
