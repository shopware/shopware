import template from './created-component.html.twig';

Shopware.Component.register('sw-created-demo', {
    template,

    inject: ['shortcutService'],

    props: {
        selector: {
            type: String,
            required: false,
            default: 'body',
        },
    },

    emits: ['ready'],

    data() {
        return {
            initialized: false,
        };
    },

    created() {
        this.shortcutService.stopEventListener();
        this.$emit('ready');
    },

    mounted() {
        this.initialized = true;
    },

    beforeUnmount() {
        this.shortcutService.startEventListener();
    },

    unmounted() {
        this.initialized = false;
    },

    methods: {
        reset() {
            this.initialized = false;
        },
    },
});
