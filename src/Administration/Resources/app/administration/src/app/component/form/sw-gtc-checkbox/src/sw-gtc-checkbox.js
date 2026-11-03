/**
 * @sw-package framework
 */

import template from './sw-gtc-checkbox.html.twig';
import { featureService } from '../dependencies';

/**
 * @private
 */
export default {
    template,

    inject: [featureService],

    emits: ['update:value'],

    props: {
        value: {
            type: Boolean,
            required: true,
        },
    },

    methods: {
        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
