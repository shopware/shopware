/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-detail.html.twig';
import './sw-users-permissions-role-detail.scss';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'privileges',
        'userService',
        'loginService',
        'acl',
        'appAclService',
        'ssoSettingsService',
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
            return Shopware.Store.get('session').languageId;
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleId() {
            return this.$route.params.id?.toLowerCase();
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
            Shopware.ExtensionAPI.publishData({
                id: 'sw-users-permissions-role-detail__detailedPrivileges',
                path: 'detailedPrivileges',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-users-permissions-role-detail__role',
                path: 'role',
                scope: this,
            });
            if (!this.roleId) {
                this.createNewRole();
                return;
            }

            this.isLoading = true;
            this.getRole().finally(() => {
                this.isLoading = false;
            });
        },

        createNewRole() {
            this.isLoading = true;

            this.role = this.roleRepository.create();

            this.role.name = '';
            this.role.description = '';
            this.role.privileges = [];

            this.isLoading = false;
        },

        async getRole() {
            await this.appAclService.addAppPermissions();

            this.role = await this.roleRepository.get(this.roleId);

            const filteredPrivileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
            const allGeneralPrivileges = this.privileges.getPrivilegesForAdminPrivilegeKeys(filteredPrivileges);

            this.detailedPrivileges = this.role.privileges.filter((privilege) => {
                return !allGeneralPrivileges.includes(privilege);
            });
            this.role.privileges = filteredPrivileges;
        },

        async onSave() {
            let isSso = false;

            try {
                this.isLoading = true;
                const response = await this.ssoSettingsService.isSso();

                isSso = response.isSso;
            } finally {
                this.isLoading = false;
            }

            if (isSso) {
                await this.saveRole({ ...Shopware.Context.api });
                return;
            }

            this.confirmPasswordModal = true;
        },

        async saveRole(context) {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.confirmPasswordModal = false;

            this.role.privileges = [
                ...this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges),
                ...this.detailedPrivileges,
            ].sort();

            try {
                await this.roleRepository.save(this.role, context);
                await this.updateCurrentUser();

                if (this.role.isNew()) {
                    this.$router.push({
                        name: 'sw.users.permissions.role.detail',
                        params: { id: this.role.id },
                    });
                }

                await this.getRole();
                this.isSaveSuccessful = true;
            } catch {
                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage',
                        {
                            entityName: this.role.name,
                        },
                        0,
                    ),
                });

                this.role.privileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
            } finally {
                this.isLoading = false;
            }
        },

        async updateCurrentUser() {
            const { data } = await this.userService.getUser();

            delete data.password;

            Shopware.Store.get('session').setCurrentUser(data);
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
};
