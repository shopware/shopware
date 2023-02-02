/**
 * @package system-settings
 */
import template from './sw-users-permissions-user-listing.html.twig';
import './sw-users-permissions-user-listing.scss';

const { Data, Mixin, State } = Shopware;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'userService',
        'loginService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

    data() {
        return {
            user: [],
            isLoading: false,
            itemToDelete: null,
            confirmDeleteModal: false,
            confirmPasswordModal: false,
            disableRouteParams: true,
            sortBy: 'username',

            // @deprecated tag:v6.6.0 - will be removed
            confirmPassword: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        userRepository() {
            return this.repositoryFactory.create('user');
        },

        currentUser: {
            get() {
                return State.get('session').currentUser;
            },
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
                label: this.$tc('sw-users-permissions.users.user-grid.labelUsername'),
            }, {
                property: 'firstName',
                label: this.$tc('sw-users-permissions.users.user-grid.labelFirstName'),
            }, {
                property: 'lastName',
                label: this.$tc('sw-users-permissions.users.user-grid.labelLastName'),
            }, {
                property: 'aclRoles',
                sortable: false,
                label: this.$tc('sw-users-permissions.users.user-grid.labelRoles'),
            }, {
                property: 'email',
                label: this.$tc('sw-users-permissions.users.user-grid.labelEmail'),
            }];
        },
    },

    methods: {
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

            this.$emit('get-list');

            return this.userRepository.search(this.userCriteria).then((users) => {
                this.total = users.total;
                this.user = users;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onDelete(user) {
            this.confirmDeleteModal = true;
            this.itemToDelete = user;
        },

        onCloseDeleteModal() {
            this.confirmDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.confirmPasswordModal = true;
        },

        deleteUser(context) {
            this.confirmPasswordModal = false;
            const user = this.itemToDelete;

            const username = `${user.firstName} ${user.lastName} `;
            const titleDeleteSuccess = this.$tc('global.default.success');
            const messageDeleteSuccess = this.$tc(
                'sw-users-permissions.users.user-grid.notification.deleteSuccess.message',
                0,
                { name: username },
            );
            const titleDeleteError = this.$tc('global.default.error');
            const messageDeleteError = this.$tc(
                'sw-users-permissions.users.user-grid.notification.deleteError.message',
                0,
                { name: username },
            );
            if (user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-users-permissions.users.user-grid.notification.deleteUserLoggedInError.message'),
                });
                return;
            }

            this.userRepository.delete(user.id, context).then(() => {
                this.createNotificationSuccess({
                    title: titleDeleteSuccess,
                    message: messageDeleteSuccess,
                });
                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: titleDeleteError,
                    message: messageDeleteError,
                });
            });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPasswordModal = false;
        },
    },
};
