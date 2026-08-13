import template from './root-el-component.html.twig';

Shopware.Component.register('sw-root-el-item', {
    template,

    data() {
        return {
            isActive: false,
        };
    },

    mounted() {
        this.$el.addEventListener('keydown', this.handleKeyDown);
    },

    beforeUnmount() {
        this.$el.removeEventListener('keydown', this.handleKeyDown);
    },

    methods: {
        handleKeyDown(event) {
            if (event.target !== this.$el) {
                return;
            }

            this.isActive = true;
        },

        scrollIntoView() {
            this.$el.scrollIntoView({ behavior: 'smooth' });
        },
    },
});
