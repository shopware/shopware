import template from './sw-settings-rule-tree-item.html.twig';

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    props: {
        association: {
            type: String,
            required: true,
        },
        hideActions: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    methods: {
        hasItemAssociation(item) {
            return item.data[this.association]?.length > 0 || item.data.extensions[this.association]?.length > 0;
        },
    },
};
