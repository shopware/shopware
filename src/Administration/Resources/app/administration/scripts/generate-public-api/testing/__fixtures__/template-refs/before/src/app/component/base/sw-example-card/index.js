import template from './sw-example-card.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    props: {
        title: {
            type: String,
            required: false,
            default: '',
        },

        isManual: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-example-card.columnName'),
                },
            ];
        },
    },
};
