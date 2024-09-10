import template from './sw-settings-rule-tree-item.html.twig';

/**
 * @private
 * @package services-settings
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        association: {
            type: String,
            required: true,
        },
        hideActions: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        hasItemAssociation(item) {
            return item.data[this.association]?.length > 0 || item.data.extensions[this.association]?.length > 0;
        },
    },
};
