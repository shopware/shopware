import template from './block-component.html.twig';

Shopware.Component.register('sw-block-card', {
    template,

    inject: ['acl'],

    props: {
        initialCount: {
            type: Number,
            required: false,
            default: 0,
        },
        readOnly: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['action', 'reset'],

    data() {
        return {
            title: 'Block Card',
            description: 'A card with extensible blocks',
            count: this.initialCount,
        };
    },

    computed: {
        canEdit() {
            return !this.readOnly && this.acl.can('product.editor');
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

        readOnly(newVal) {
            if (newVal) {
                this.title = 'Read-only mode';
            }
        },
    },

    methods: {
        onAction() {
            this.count += 1;
            this.$emit('action', this.count);
        },

        onReset() {
            this.count = this.initialCount;
            this.title = 'Block Card';
            this.$refs.cardWrapper.focus();
            this.$emit('reset');
        },
    },

    mounted() {
        this.count = this.initialCount;
    },
});
