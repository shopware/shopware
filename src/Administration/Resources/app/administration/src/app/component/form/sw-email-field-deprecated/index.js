import template from './sw-email-field-deprecated.html.twig';

const { Component } = Shopware;

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
Component.extend('sw-email-field-deprecated', 'sw-text-field-deprecated', {
    template,

    emits: [
        'inheritance-restore',
        'inheritance-remove',
    ],
});
