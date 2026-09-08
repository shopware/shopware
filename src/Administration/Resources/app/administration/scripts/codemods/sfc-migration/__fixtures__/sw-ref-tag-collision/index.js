import template from './sw-ref-tag-collision.html.twig';

export default {
    template,

    methods: {
        focusResults() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },
    },
};
