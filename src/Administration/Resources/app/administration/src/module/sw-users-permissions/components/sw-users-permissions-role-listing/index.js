import template from './sw-users-permissions-role-listing.html.twig';
import './sw-users-permissions-role-listing.scss';

const { Component, Data, Mixin } = Shopware;
const { Criteria } = Data;

Component.register('sw-users-permissions-role-listing', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            roles: [],
            isLoading: false,
            itemToDelete: null,
            disableRouteParams: true
        };
    },

    computed: {
        rolesColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-users-permissions.roles.role-grid.labelName')
            }, {
                property: 'description',
                label: this.$tc('sw-users-permissions.roles.role-grid.labelDescription')
            }];
        },

        roleRepository() {
            return Shopware.Service('repositoryFactory').create('acl_role');
        },

        roleCriteria() {
            const criteria = new Criteria(this.page, this.limit);

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
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            this.roles = [];

            return this.roleRepository.search(this.roleCriteria, Shopware.Context.api).then((roles) => {
                this.roles = roles;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onSearch(searchTerm) {
            this.term = searchTerm;
            this.getList();
        },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }
            return this.itemToDelete.id === item.id;
        },

        onDelete(role) {
            this.itemToDelete = role;
        },

        onCloseDeleteModal() {
            this.itemToDelete = null;
        },

        onConfirmDelete(role) {
            this.roleRepository.delete(role.id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-users-permissions.roles.role-grid.notification.deleteSuccess.title'),
                    message: this.$tc('sw-users-permissions.roles.role-grid.notification.deleteSuccess.message',
                        0,
                        { name: role.name })
                });

                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-users-permissions.roles.role-grid.notification.deleteError.title'),
                    message: this.$tc('sw-users-permissions.roles.role-grid.notification.deleteError.message',
                        0,
                        { name: role.name })
                });
            });

            this.onCloseDeleteModal();
        }
    }
});
