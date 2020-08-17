import template from './sw-verify-user-modal.html.twig';

const { Component, Mixin, State } = Shopware;
const types = Shopware.Utils.types;

Component.register('sw-verify-user-modal', {
    template,

    inject: [
        'loginService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            confirmPassword: ''
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },

        async onSubmitConfirmPassword() {
            const verifiedToken = await this.verifyUserToken();

            if (!verifiedToken) {
                return;
            }

            const context = { ...Shopware.Context.api };
            context.authToken.access = verifiedToken;

            this.$emit('verified', context);
        },

        verifyUserToken() {
            const { username } = State.get('session').currentUser;

            return this.loginService.verifyUserByUsername(username, this.confirmPassword).then(({ access }) => {
                this.confirmPassword = '';

                if (types.isString(access)) {
                    return access;
                }

                return false;
            }).catch(() => {
                this.confirmPassword = '';
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorTitle'),
                    message: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorMessage')
                });

                return false;
            });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        }
    }
});
