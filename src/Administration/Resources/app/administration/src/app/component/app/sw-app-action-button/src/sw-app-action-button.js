/**
 * @sw-package framework
 */

import template from './sw-app-action-button.html.twig';
import './sw-app-action-button.scss';
import { aclService, getAppContext, useSessionStore } from '../dependencies';

/**
 * @private
 */
export default {
    template,

    inject: [aclService],

    emits: ['run-app-action'],

    props: {
        action: {
            type: Object,
            required: true,
        },
    },

    computed: {
        buttonLabel() {
            const currentLocale = useSessionStore().currentLocale;
            const fallbackLocale = getAppContext().fallbackLocale;

            if (typeof this.action.label === 'string') {
                return this.action.label;
            }

            return this.action.label[currentLocale] || this.action.label[fallbackLocale] || '';
        },
    },

    methods: {
        runAction() {
            this.$emit('run-app-action', this.action);
        },
    },
};
