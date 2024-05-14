import template from './sw-email-field.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Simple email field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-email-field label="Name" placeholder="The placeholder goes here..."></sw-email-field>
 */
Component.extend('sw-email-field', 'sw-text-field-deprecated', {
    template,
});
