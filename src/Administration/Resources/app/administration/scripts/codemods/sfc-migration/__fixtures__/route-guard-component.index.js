import template from './route-guard-component.html.twig';

Shopware.Component.register('sw-route-guard-page', {
    template,

    data() {
        return {
            isDirty: false,
        };
    },

    methods: {
        confirmLeave() {
            return window.confirm('Discard changes?');
        },
    },

    mounted() {
        this.isDirty = false;
    },

    beforeRouteLeave(to, from, next) {
        if (this.isDirty && !this.confirmLeave()) {
            next(false);

            return;
        }

        next();
    },

    async beforeRouteUpdate(to, from, next) {
        await this.$nextTick();

        this.isDirty = false;
        next();
    },
});
