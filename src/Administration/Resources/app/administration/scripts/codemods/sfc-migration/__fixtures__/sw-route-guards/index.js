import template from './sw-route-guards.html.twig';

export default {
    template,

    data() {
        return {
            hasUnsavedChanges: false,
            loadedId: null,
        };
    },

    beforeRouteLeave(to, from, next) {
        if (this.hasUnsavedChanges) {
            next(false);
            return;
        }

        next();
    },

    beforeRouteUpdate(to) {
        this.loadedId = to.params.id;
    },

    methods: {
        discard() {
            this.hasUnsavedChanges = false;
        },
    },
};
