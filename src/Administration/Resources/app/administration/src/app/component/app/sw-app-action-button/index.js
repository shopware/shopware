/**
 * @package admin
 */

import template from './sw-app-action-button.html.twig';
import './sw-app-action-button.scss';

const { Component, State, Context } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-app-action-button', {
    template,

    inject: ['acl'],

    props: {
        action: {
            type: Object,
            required: true,
        },
    },

    computed: {
        buttonLabel() {
            const currentLocale = State.get('session').currentLocale;
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

