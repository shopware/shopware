import template from './sw-login.html.twig';
import './sw-login.less';

Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login'
    },

    watch: {
        token() {
            if (!this.token.length || this.expiry === -1) {
                return;
            }

            this.$router.push({
                name: 'core'
            });
        }
    },

    template
});
