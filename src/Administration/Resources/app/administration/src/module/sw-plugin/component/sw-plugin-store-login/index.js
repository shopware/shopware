import template from './sw-plugin-store-login.html.twig';
import './sw-plugin-store-login.scss';

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12608) tag:v6.4.0
 * Deprecation notice: The whole plugin manager will be removed with 6.4.0 and replaced
 * by the extension module.
 * When removing the feature flag for FEATURE_NEXT_12608, also merge the merge request
 * for NEXT-13821 which removes the plugin manager.
 */

const { Component, State, Mixin } = Shopware;

Component.register('sw-plugin-store-login', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            shopwareId: null,
            password: null
        };
    },

    methods: {
        onLoginClick() {
            State.dispatch(
                'swPlugin/loginShopwareUser', {
                    shopwareId: this.shopwareId,
                    password: this.password
                }
            ).then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-plugin.store-login.loginMessage')
                });
                this.$emit('close-modal');
            });
        },

        onCloseModal() {
            this.$emit('close-modal');
        }
    }
});
