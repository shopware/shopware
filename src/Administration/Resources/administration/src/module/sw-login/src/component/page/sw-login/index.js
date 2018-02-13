import { Component } from 'src/core/shopware';
import template from './sw-login.html.twig';
import './sw-login.less';

Component.register('sw-login', {
    stateMapping: {
        state: 'login'
    },

    watch: {
        'login.token'() {
            if (!this.login.token.length || this.login.expiry === -1) {
                return;
            }

            this.$router.push({
                name: 'core'
            });
        }
    },

    template
});
