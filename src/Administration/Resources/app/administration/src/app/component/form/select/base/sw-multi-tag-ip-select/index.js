import template from './sw-multi-tag-ip-select.html.twig';

const { Component } = Shopware;
const { string } = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description Renders a multi select field for ip addresses specifically. The corresponding validation method
 * is active by default.
 * @example-type static
 * @component-example
 * <sw-multi-tag-ip-select
 *     :value="['127.0.0.1', '10.0.0.1', '::']"
 * ></sw-multi-tag-ip-select>
 */
Component.extend('sw-multi-tag-ip-select', 'sw-multi-tag-select', {
    template,

    props: {
        validate: {
            type: Function,
            required: false,
            default: searchTerm => string.isValidIp(searchTerm)
        }
    },

    computed: {
        errorObject() {
            const err = !this.inputIsValid && this.searchTerm.length > 0;

            return err ? { code: 'SHOPWARE_INVALID_IP' } : null;
        }
    }
});
