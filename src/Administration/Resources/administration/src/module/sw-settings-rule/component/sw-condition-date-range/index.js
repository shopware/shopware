import { Component } from 'src/core/shopware';
import template from './sw-condition-date-range.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-date-range :condition="condition"></sw-condition-date-range>
 */
Component.extend('sw-condition-date-range', 'sw-condition-base', {
    template,

    computed: {
        fieldNames() {
            return ['fromDate', 'toDate', 'useTime'];
        },
        defaultValues() {
            return {
                useTime: false
            };
        }
    }
});
