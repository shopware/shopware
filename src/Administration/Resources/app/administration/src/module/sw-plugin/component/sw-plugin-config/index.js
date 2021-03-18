import template from './sw-plugin-config.html.twig';

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12608) tag:v6.4.0
 * Deprecation notice: The whole plugin manager will be removed with 6.4.0 and replaced
 * by the extension module.
 * When removing the feature flag for FEATURE_NEXT_12608, also merge the merge request
 * for NEXT-13821 which removes the plugin manager.
 */

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
