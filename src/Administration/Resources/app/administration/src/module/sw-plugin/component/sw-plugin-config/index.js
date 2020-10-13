import template from './sw-plugin-config.html.twig';

const { Mixin } = Shopware;

Shopware.Component.register('sw-plugin-config', {
    mixins: [
        Mixin.getByName('notification')
    ],

    template,

    props: {
        namespace: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            salesChannelId: null,
            config: {},
            actualConfigData: {}
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
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    message: err
                });
            });
        }
    }
});
