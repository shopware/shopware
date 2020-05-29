import template from './sw-users-permissions-user-listing.html.twig';
import './sw-users-permissions-user-listing.scss';

const { Component, Data, Mixin, State, StateDeprecated } = Shopware;
const { Criteria } = Data;

Component.register('sw-users-permissions-user-listing', {
    template,

    inject: [
        'userService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('salutation')
    ],

    data() {
        return {
            user: [],
            isLoading: false,
            itemToDelete: null,
            disableRouteParams: true
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        /** @deprecated tag:6.4.0 will be removed in v.6.4.0 */
        userStore() {
            return StateDeprecated.getStore('user');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        currentUser: {
            get() {
                return State.get('session').currentUser;
            },
            /** deprecated tag:v6.4.0 will be read only in v.6.4.0 */
            set() {}

        },

        userCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'ASC'));
            }

            criteria.addAssociation('aclRoles');

            return criteria;
        },

        userColumns() {
            return [{
                property: 'username',
                label: this.$tc('sw-users-permissions.users.user-grid.labelUsername')
            }, {
                property: 'firstName',
                label: this.$tc('sw-users-permissions.users.user-grid.labelFirstName')
            }, {
                property: 'lastName',
                label: this.$tc('sw-users-permissions.users.user-grid.labelLastName')
            }, {
                property: 'aclRoles',
                label: this.$tc('sw-users-permissions.users.user-grid.labelRoles')
            }, {
                property: 'email',
                label: this.$tc('sw-users-permissions.users.user-grid.labelEmail')
            }];
        }
    },

    methods: {
        /** @deprecated tag:6.4.0 will be removed in v.6.4.0 */
        createdComponent() { /* nth because deprecated */ },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }
            return this.itemToDelete.id === item.id;
        },

        onSearch(value) {
            this.term = value;
            this.getList();
        },

        getList() {
            this.isLoading = true;
            this.user = [];

            return this.userRepository.search(this.userCriteria, Shopware.Context.api).then((users) => {
                this.user = users;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onDelete(user) {
            this.itemToDelete = user;
        },

        onConfirmDelete(user) {
            const username = `${user.firstName} ${user.lastName} `;
            const titleDeleteSuccess = this.$tc('sw-users-permissions.users.user-grid.notification.deleteSuccess.title');
            const messageDeleteSuccess = this.$tc('sw-users-permissions.users.user-grid.notification.deleteSuccess.message',
                0,
                { name: username });
            const titleDeleteError = this.$tc('sw-users-permissions.users.user-grid.notification.deleteError.title');
            const messageDeleteError = this.$tc(
                'sw-users-permissions.users.user-grid.notification.deleteError.message', 0, { name: username }
            );
            if (user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$tc('sw-users-permissions.users.user-grid.notification.deleteUserLoggedInError.title'),
                    message: this.$tc('sw-users-permissions.users.user-grid.notification.deleteUserLoggedInError.message')
                });
                return;
            }

            this.userRepository.delete(user.id, Shopware.Context.api).then(() => {
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
