import template from './sw-created-pattern.html.twig';

export default {
    template,

    data() {
        return {
            ready: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.ready = true;
        },
    },
};
