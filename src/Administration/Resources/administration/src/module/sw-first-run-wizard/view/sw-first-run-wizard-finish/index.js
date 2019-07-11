import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-finish.html.twig';
import './sw-first-run-wizard-finish.scss';

Component.register('sw-first-run-wizard-finish', {
    template,

    inject: ['addNextCallback'],

    data() {
        return {
            edition: 'edition',
            restarting: false
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.addNextCallback(this.onFinish);
        },

        onFinish() {
            this.restarting = true;

            return Promise.resolve(false);
        }
    }
});
