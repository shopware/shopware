import template from './composables-component.html.twig';

Shopware.Component.register('sw-composables', {
    template,

    data() {
        return {
            value: '',
        };
    },

    computed: {
        currentRoute() {
            return this.$route.name;
        },

        hasSlot() {
            return !!this.$slots.default;
        },

        label() {
            return this.$tc('sw.composables.label', 2);
        },
    },

    methods: {
        goBack() {
            this.$router.back();
        },

        async update() {
            this.value = 'new';
            await this.$nextTick();
        },

        getTitle() {
            return this.$t('sw.composables.title');
        },

        focusItem() {
            this.$el.querySelector('.item').focus();
        },

        getAttrsClass() {
            return this.$attrs.class ?? '';
        },
    },
});
