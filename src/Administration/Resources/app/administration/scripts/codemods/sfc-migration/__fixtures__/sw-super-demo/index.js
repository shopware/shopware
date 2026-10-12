import template from './sw-super-demo.html.twig';

export default {
    template,

    methods: {
        mountedComponent() {
            this.$super('mountedComponent');
        },
    },
};
