import template from './sw-data-grid-column-boolean.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-data-grid-column-boolean',

    template,

    props: {
        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false
        },
        value: {
            required: true
        }
    }
};
