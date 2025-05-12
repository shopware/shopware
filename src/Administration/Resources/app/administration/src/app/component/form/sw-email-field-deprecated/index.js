import template from './sw-email-field-deprecated.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @description Simple email field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-email-field label="Name" placeholder="The placeholder goes here..."></sw-email-field>
 */
export default {
    template,

    emits: [
        'inheritance-restore',
        'inheritance-remove',
    ],
};
