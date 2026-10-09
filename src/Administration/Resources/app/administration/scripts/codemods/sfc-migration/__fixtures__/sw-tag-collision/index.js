import template from './sw-tag-collision.html.twig';

export default {
    template,

    props: {
        routerLink: {
            type: Object,
            required: false,
            default: null,
        },
    },
};
