import template from './sw-plugin-config.html.twig';

export default {
    name: 'sw-plugin-config',

    template,

    data() {
        return {
            namespace: this.$route.params.namespace,
            domain: `bundle.${this.$route.params.namespace}`,
            salesChannelId: null,
            config: {},
            actualConfigData: {}
        };
    }
};
