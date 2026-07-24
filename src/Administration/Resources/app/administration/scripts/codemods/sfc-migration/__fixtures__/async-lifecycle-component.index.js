import template from './async-lifecycle-component.html.twig';

Shopware.Component.register('sw-async-lifecycle', {
    template,

    data() {
        return {
            loaded: false,
        };
    },

    async created() {
        await this.bootstrap();
    },

    async mounted() {
        await this.loadData();
        this.loaded = true;
    },

    methods: {
        async bootstrap() {
            await Promise.resolve();
        },

        async loadData() {
            await Promise.resolve();
        },
    },
});
