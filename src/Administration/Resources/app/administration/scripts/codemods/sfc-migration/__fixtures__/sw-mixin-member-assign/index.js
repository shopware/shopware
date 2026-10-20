import template from './sw-mixin-member-assign.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('media-grid-listener'),
    ],

    props: {
        items: {
            type: Array,
            required: true,
        },

        allowMultiSelect: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        selectableItems() {
            return this.items;
        },
    },

    created() {
        if (this.allowMultiSelect) {
            return;
        }

        // Swapping the mixin's handler out on the instance; the composable hands it over as a const.
        this.handleMediaItemClicked = ({ item }) => {
            this.showDetails(item);
        };
    },
};
