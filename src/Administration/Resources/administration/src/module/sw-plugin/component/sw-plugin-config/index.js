import { Mixin } from 'src/core/shopware';
import template from './sw-plugin-config.html.twig';

export default {
    name: 'sw-plugin-config',

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    provide() {
        return {
            getDetailComponent: () => {
                return this;
            }
        };
    },

    template,

    data() {
        return {
            namespace: this.$route.params.namespace,
            domain: `bundle.${this.$route.params.namespace}`,
            salesChannelId: null,
            config: {},
            actualConfigData: {},
            promises: []
        };
    },

    methods: {
        onSave() {
            this.$emit('save');

            Promise.all(this.promises).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
            }).catch((err) => {
                console.dir('err : ', err);
                this.createNotificationError({
                    title: this.$tc('sw-plugin-config.titleSaveError'),
                    message: err
                });
            });
        },
        onSaveConfig(promise) {
            this.promises.push(promise);
        }
    }
};
