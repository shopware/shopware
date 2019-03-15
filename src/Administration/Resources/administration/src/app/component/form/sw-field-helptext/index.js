import template from './sw-field-helptext.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-field-helptext',
    template,

    props: {
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        id: {
            type: String,
            required: true
        }
    }
};
