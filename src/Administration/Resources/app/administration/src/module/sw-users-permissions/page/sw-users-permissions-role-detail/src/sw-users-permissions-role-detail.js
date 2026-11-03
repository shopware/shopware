/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-detail.html.twig';
import './sw-users-permissions-role-detail.scss';
import {
    aclService,
    appAclService,
    featureService,
    getApiContext,
    loginService,
    notificationMixin,
    privilegesService,
    publishData,
    repositoryFactoryService,
    ssoSettingsService,
    userService,
    useSessionStore,
} from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [
        featureService,
        repositoryFactoryService,
        privilegesService,
        userService,
        loginService,
        aclService,
        appAclService,
        ssoSettingsService,
    ],

    mixins: [notificationMixin],

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
            return useSessionStore().languageId;
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleId() {
            return this.$route.params.id?.toLowerCase();
        },

        roleDetailTabs() {
            const createRouteTab = (label, routeName) => {
                const route = {
                    name: routeName,
                    params: { id: this.$route.params.id },
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                };
            };

            return [
                createRouteTab('sw-users-permissions.roles.tabs.general', 'sw.users.permissions.role.detail.general'),
                createRouteTab(
                    'sw-users-permissions.roles.tabs.detailed',
                    'sw.users.permissions.role.detail.detailed-privileges',
                ),
            ];
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
            publishData({
                id: 'sw-users-permissions-role-detail__detailedPrivileges',
                path: 'detailedPrivileges',
                scope: this,
            });
            publishData({
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
            this.detailedPrivileges = [...this.privileges.getDefaultUserPrivileges()];

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
                await this.saveRole({ ...getApiContext() });
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
                    message: this.$t(
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

            useSessionStore().setCurrentUser(data);
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
