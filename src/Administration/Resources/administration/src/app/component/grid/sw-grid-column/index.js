import './sw-grid-column.less';
import template from './sw-grid-column.html.twig';

export default Shopware.Component.register('sw-grid-column', {

    props: {
        label: {
            type: String,
            required: true
        },
        flex: {
            required: false,
            default: 1
        }
    },

    created() {
        this.registerColumn();
    },

    methods: {
        registerColumn() {
            const hasColumn = this.$parent.columns.findIndex((column) => column.label === this.label);

            if (hasColumn === -1) {
                this.$parent.columns.push({
                    label: this.label,
                    flex: this.flex
                });
            }
        }
    },

    template
});
