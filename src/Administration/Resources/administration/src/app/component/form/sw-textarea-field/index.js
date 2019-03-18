import template from './sw-textarea-field.html.twig';

/**
 * @description textarea input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-textarea-field type="textarea" label="Name" placeholder="placeholder goes here..."></sw-textarea-field>
 */
export default {
    name: 'sw-textarea-field',
    extendsFrom: 'sw-text-field',
    template,

    computed: {
        typeFieldClass() {
            return 'sw-field--textarea';
        }
    }
};
