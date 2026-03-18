import template from './block-component.html.twig';

Shopware.Component.register('sw-block-card', {
    template,

    inject: ['acl'],

    data() {
        return {
            title: 'Block Card',
            description: 'A card with extensible blocks',
            count: 0,
        };
    },

    computed: {
        canEdit() {
            return this.acl.can('product.editor');
        },

        label: {
            get() {
                return this.title;
            },
            set(val) {
                this.title = val;
            },
        },
    },

    watch: {
        count(newVal) {
            if (newVal > 10) {
                this.title = 'Limit reached';
            }
        },
    },

    methods: {
        onAction() {
            this.count += 1;
            this.$emit('action', this.count);
        },
    },

    mounted() {
        this.count = 0;
    },
});
