import template from './sw-users-permissions-role-detail.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-users-permissions-role-detail', {
    template,

    inject: [
        'repositoryFactory',
        'privileges',
        'userService',
        'loginService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],


    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            role: null,
            confirmPasswordModal: false,
            detailedPrivileges: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleId() {
            return this.$route.params.id;
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.roleId) {
                this.createNewRole();
                return;
            }

            this.getRole();
        },

        createNewRole() {
            this.isLoading = true;

            this.role = this.roleRepository.create();

            this.role.name = '';
            this.role.description = '';
            this.role.privileges = [];

            this.isLoading = false;
        },

        getRole() {
            this.isLoading = true;

            this.roleRepository.get(this.roleId)
                .then((role) => {
                    this.role = role;

                    const filteredPrivileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
                    const allGeneralPrivileges = this.privileges.getPrivilegesForAdminPrivilegeKeys(filteredPrivileges);

                    this.detailedPrivileges = this.role.privileges.filter(privilege => {
                        return !allGeneralPrivileges.includes(privilege);
                    });
                    this.role.privileges = filteredPrivileges;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSave() {
            this.confirmPasswordModal = true;
        },

        saveRole(context) {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.role.privileges = [
                ...this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges),
                ...this.detailedPrivileges,
            ].sort();

            this.confirmPasswordModal = false;

            return this.roleRepository.save(this.role, context)
                .then(() => {
                    return this.updateCurrentUser();
                }).then(() => {
                    if (this.role.isNew()) {
                        this.$router.push({ name: 'sw.users.permissions.role.detail', params: { id: this.role.id } });
                    }

                    this.getRole();
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc(
                            'global.notification.notificationSaveErrorMessage',
                            0,
                            { entityName: this.role.name },
                        ),
                    });

                    this.role.privileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCurrentUser() {
            return this.userService.getUser().then((response) => {
                const data = response.data;
                delete data.password;

                return Shopware.State.commit('setCurrentUser', data);
            });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPasswordModal = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        },
    },
});
