import template from './sw-inheritance-warning.html.twig';
import './sw-inheritance-warning.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description
 * Renders inheritance warning
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-inheritance-warning :name="'This product'"></sw-inheritance-warning>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-inheritance-warning', {
    template,
    props: {
        name: {
            type: String,
            required: true,
        },
    },
});
