/**
 * @package system-settings
 */
import template from './sw-users-permissions-detailed-additional-permissions.html.twig';
import './sw-users-permissions-detailed-additional-permissions.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'privileges',
        'aclApiService',
    ],

    props: {
        role: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        detailedPrivileges: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            detailedAdditionalPermissions: [],
        };
    },

    computed: {
        allGeneralSelectedPrivileges() {
            return this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setDetailedAdditionalPermissions();
        },

        setDetailedAdditionalPermissions() {
            this.aclApiService.additionalPrivileges().then((additionalPrivileges) => {
                const roles = {};
                additionalPrivileges.forEach((privilege) => {
                    roles[privilege] = {
                        privileges: [privilege],
                        dependencies: [],
                    };
                });

                this.detailedAdditionalPermissions.push({
                    category: 'additional_permissions',
                    parent: null,
                    key: 'routes',
                    roles: roles,
                });
            });
        },

        isEntitySelected(identifier) {
            const allPrivileges = [
                ...this.allGeneralSelectedPrivileges,
                ...this.detailedPrivileges,
            ];

            return allPrivileges.includes(identifier);
        },

        isEntityDisabled(identifier) {
            if (this.disabled) {
                return true;
            }

            return this.allGeneralSelectedPrivileges.includes(identifier);
        },

        changePermissionForEntity(identifier) {
            const privilegeIndex = this.detailedPrivileges.indexOf(identifier);

            if (privilegeIndex >= 0) {
                this.detailedPrivileges.splice(privilegeIndex, 1);
                return;
            }

            this.detailedPrivileges.push(identifier);
        },
    },
};
