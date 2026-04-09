/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-listing.html.twig';
import './sw-users-permissions-role-listing.scss';

const { Data, Mixin } = Shopware;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'ssoSettingsService',
    ],

    emits: ['get-list'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            roles: [],
            isLoading: false,
            itemToDelete: null,
            disableRouteParams: true,
            isConfirmDeleteModalOpen: false,
            isConfirmingPasswordModalOpen: false,
        };
    },

    computed: {
        rolesColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-users-permissions.roles.role-grid.labelName'),
                },
                {
                    property: 'description',
                    label: this.$tc('sw-users-permissions.roles.role-grid.labelDescription'),
                },
            ];
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            // Roles created by apps should not be visible and editable in the admin
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'ASC'));
            }

            return criteria;
        },

        showListingResults() {
            if (this.isLoading) {
                return false;
            }

            return (this.roles && this.roles.length > 0) || (this.term && this.term.length <= 0);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('get-list');
        },

        getList() {
            this.isLoading = true;
            this.roles = [];

            return this.roleRepository
                .search(this.roleCriteria)
                .then((roles) => {
                    this.total = roles.total;
                    this.roles = roles;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSearch(searchTerm) {
            this.term = searchTerm;

            this.$emit('get-list');
        },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }
            return this.itemToDelete.id === item.id;
        },

        onDelete(role) {
            this.itemToDelete = role;
            this.isConfirmDeleteModalOpen = true;
        },

        onCloseDeleteModal() {
            this.isConfirmDeleteModalOpen = false;
        },

        onConfirmDelete() {
            this.isLoading = true;
            this.onCloseDeleteModal();

            this.ssoSettingsService.isSso().then((response) => {
                this.isLoading = false;

                if (response.isSso) {
                    this.deleteRole({ ...Shopware.Context.api });

                    return;
                }

                this.isConfirmingPasswordModalOpen = true;
            });
        },

        deleteRole(context) {
            this.isConfirmingPasswordModalOpen = false;
            const role = this.itemToDelete;
            this.itemToDelete = null;
            this.isLoading = true;

            this.roleRepository
                .delete(role.id, context)
                .then(() => {
                    this.isLoading = false;
                    this.createNotificationSuccess({
                        message: this.$tc(
                            'sw-users-permissions.roles.role-grid.notification.deleteSuccess.message',
                            {
                                name: role.name,
                            },
                            0,
                        ),
                    });

                    this.$emit('get-list');
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$tc(
                            'sw-users-permissions.roles.role-grid.notification.deleteError.message',
                            {
                                name: role.name,
                            },
                            0,
                        ),
                    });
                });
        },

        onCloseConfirmPasswordModal() {
            this.isConfirmingPasswordModalOpen = false;
        },
    },
};
