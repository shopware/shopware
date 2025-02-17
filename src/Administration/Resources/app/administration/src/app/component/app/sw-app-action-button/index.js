/**
 * @sw-package framework
 */

import template from './sw-app-action-button.html.twig';
import './sw-app-action-button.scss';

const { Component, Context } = Shopware;

/**
 * @private
 */
Component.register('sw-app-action-button', {
    template,

    inject: ['acl'],

    emits: ['run-app-action'],

    props: {
        action: {
            type: Object,
            required: true,
        },
    },

    computed: {
        buttonLabel() {
            const currentLocale = Shopware.Store.get('session').currentLocale;
            const fallbackLocale = Context.app.fallbackLocale;

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
});
