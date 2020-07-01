import template from './sw-users-permissions-role-detail.html.twig';
import './sw-users-permissions-role-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-users-permissions-role-detail', {
    template,

    inject: ['repositoryFactory', 'privileges', 'userService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            role: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapPropertyErrors('role', [
            'name',
            'description'
        ]),

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
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

        requiredPrivileges() {
            return this.privileges.getRequiredPrivileges();
        }
    },

    watch: {
        languageId() {
            this.createdComponent();
        }
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

            this.role = this.roleRepository.create(Shopware.Context.api);

            this.role.name = '';
            this.role.description = '';
            this.role.privileges = [];

            this.isLoading = false;
        },

        getRole() {
            this.isLoading = true;

            this.roleRepository.get(this.roleId, Shopware.Context.api)
                .then((role) => {
                    this.role = role;
                    this.role.privileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.role.privileges = [
                ...this.getPrivilegesForSelections(),
                ...this.requiredPrivileges
            ];

            return this.roleRepository.save(this.role, Shopware.Context.api)
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
                        title: this.$tc('global.default.error'),
                        message: this.$tc(
                            'global.notification.notificationSaveErrorMessage',
                            0,
                            { entityName: this.role.name }
                        )
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

        getPrivilegesForSelections() {
            const privileges = [];

            this.role.privileges.forEach(privilegeKey => {
                const privilegeRole = this.privileges.getPrivilegeRole(privilegeKey);

                privileges.push(privilegeKey, ...privilegeRole.privileges);
            });

            return privileges;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        }
    }
});
