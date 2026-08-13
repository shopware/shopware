import template from './provide-component.html.twig';

Shopware.Component.register('sw-provider-card', {
    template,

    provide() {
        return {
            registerCardItem: this.registerCardItem,
            'card-scope': this.scopeName,
        };
    },

    emits: ['item-register'],

    data() {
        return {
            items: [],
            scopeName: 'sw-provider-card',
        };
    },

    computed: {
        itemCount() {
            return this.items.length;
        },
    },

    methods: {
        registerCardItem(item) {
            this.items.push(item);
            this.$emit('item-register', item);
        },
    },
});
