import template from './sw-data-grid-settings.html.twig';
import './sw-data-grid-settings.scss';

/**
 * @private
 */
export default {
    name: 'sw-data-grid-settings',

    template,

    props: {
        columns: {
            type: Array,
            default() {
                return [];
            },
            required: true
        },
        compact: {
            type: Boolean,
            required: true,
            default: false
        },
        disabled: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    data() {
        return {
            currentCompact: false,
            currentColumns: []
        };
    },

    watch: {
        columns() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currentCompact = this.compact;
            this.currentColumns = this.columns;
        },

        onChangeCompactMode(value) {
            this.$emit('change-compact-mode', value);
        },

        onChangeColumnVisibility(value, index) {
            this.$emit('change-column-visibility', value, index);
        },

        onClickChangeColumnOrderUp(columnIndex) {
            this.$emit('change-column-order', columnIndex, columnIndex - 1);
        },

        onClickChangeColumnOrderDown(columnIndex) {
            this.$emit('change-column-order', columnIndex, columnIndex + 1);
        }
    }
};
