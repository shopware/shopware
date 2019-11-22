import template from './sw-settings-user-list.html.twig';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-settings-user-list', {
    template,

    inject: ['userService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    data() {
        return {
            currentUser: null,
            total: 0,
            user: [],
            term: this.$route.query ? this.$route.query.searchTerm : '',
            isLoading: false,
            itemToDelete: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        userStore() {
            return StateDeprecated.getStore('user');
        },

        userColumns() {
            return [{
                property: 'username',
                label: this.$tc('sw-settings-user.user-grid.labelUsername')
            }, {
                property: 'firstName',
                label: this.$tc('sw-settings-user.user-grid.labelFirstName')
            }, {
                property: 'lastName',
                label: this.$tc('sw-settings-user.user-grid.labelLastName')
            }, {
                property: 'email',
                label: this.$tc('sw-settings-user.user-grid.labelEmail')
            }];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }
            return this.itemToDelete.id === item.id;
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.user = [];

            return this.userStore.getList(params).then((response) => {
                this.user = response.items;
                this.total = response.total;
                this.isLoading = false;

                return this.user;
            });
        },

        onDelete(user) {
            this.itemToDelete = user;
        },

        onConfirmDelete(user) {
            const username = `${user.firstName} ${user.lastName} `;
            const titleDeleteSuccess = this.$tc('sw-settings-user.user-grid.notification.deleteSuccess.title');
            const messageDeleteSuccess = this.$tc('sw-settings-user.user-grid.notification.deleteSuccess.message',
                0,
                { name: username });
            const titleDeleteError = this.$tc('sw-settings-user.user-grid.notification.deleteError.title');
            const messageDeleteError = this.$tc(
                'sw-settings-user.user-grid.notification.deleteError.message', 0, { name: username }
            );
            if (user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-grid.notification.deleteUserLoggedInError.title'),
                    message: this.$tc('sw-settings-user.user-grid.notification.deleteUserLoggedInError.message')
                });
                return;
            }
            user.delete(true).then(() => {
                this.createNotificationSuccess({
                    title: titleDeleteSuccess,
                    message: messageDeleteSuccess
                });
                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: titleDeleteError,
                    message: messageDeleteError
                });
            });
            this.onCloseDeleteModal();
        },

        onCloseDeleteModal() {
            this.itemToDelete = null;
        }
    }
});
