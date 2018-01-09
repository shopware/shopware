import template from './sw-login.html.twig';
import './sw-login.less';

Shopware.Component.register('sw-login', {
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
