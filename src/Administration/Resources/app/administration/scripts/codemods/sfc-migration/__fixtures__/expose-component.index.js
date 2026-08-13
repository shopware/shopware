import template from './expose-component.html.twig';

Shopware.Component.register('sw-expose-card', {
    template,

    expose: [
        'focus',
        'isOpen',
    ],

    data() {
        return {
            isOpen: false,
        };
    },

    computed: {
        stateLabel() {
            return this.isOpen ? 'open' : 'closed';
        },
    },

    methods: {
        focus() {
            this.isOpen = true;
        },
    },
});
