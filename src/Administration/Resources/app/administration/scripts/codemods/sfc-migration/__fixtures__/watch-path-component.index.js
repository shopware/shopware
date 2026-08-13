import template from './watch-path-component.html.twig';

Shopware.Component.register('sw-watch-path-card', {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            entity: null,
            netPrice: 0,
            label: '',
        };
    },

    watch: {
        'item.price.net'(value) {
            this.netPrice = value;
        },

        'entity.name': {
            handler: 'applyLabel',
            immediate: true,
        },

        'entity.customFields': {
            handler(value) {
                this.label = value?.title ?? '';
            },
            deep: true,
        },

        '$route.name'() {
            this.label = '';
        },
    },

    methods: {
        applyLabel(name) {
            this.label = name ?? '';
        },
    },
});
