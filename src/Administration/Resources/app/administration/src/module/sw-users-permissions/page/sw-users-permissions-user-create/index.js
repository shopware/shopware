import template from './sw-users-permissions-user-create.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.extend('sw-users-permissions-user-create', 'sw-users-permissions-user-detail', {
    template,

    computed: {
        ...mapPropertyErrors('user', [
            'password',
        ]),
    },

    methods: {
        loadUser() {
            if (this.user) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                this.user = this.userRepository.create(this.context);
                this.user.admin = false;
                resolve();
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.users.permissions.user.detail', params: { id: this.user.id } });
        },

        onSave() {
            if (!this.user.localeId) {
                this.user.localeId = this.currentUser.localeId;
            }
            this.$super('onSave');
        },
    },
});
