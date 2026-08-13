import template from './device-component.html.twig';

Shopware.Component.register('sw-device-card', {
    template,

    data() {
        return {
            isCompact: false,
        };
    },

    computed: {
        systemKey() {
            return this.$device.getSystemKey();
        },
    },

    mounted() {
        this.isCompact = this.$device.getViewportWidth() < 500;
    },
});
