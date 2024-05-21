import template from './sw-email-field-deprecated.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 * @deprecated tag:v6.7.0  - Will be removed use mt-email-field instead.
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
});
