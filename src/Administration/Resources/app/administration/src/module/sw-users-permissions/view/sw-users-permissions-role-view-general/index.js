/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-role-view-general.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'feature',
    ],

    props: {
        role: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            mcpIntegrations: [],
            showMcpModal: false,
        };
    },

    computed: {
        ...mapPropertyErrors('role', [
            'name',
            'description',
        ]),

        roleId() {
            if (!this.role || this.role.isNew()) {
                return null;
            }

            return this.role.id;
        },

        integrationRepository() {
            return this.repositoryFactory.create('integration');
        },

        isReadOnly() {
            return !this.acl.can('users_and_permissions.editor') || undefined;
        },

        shouldShowMcpHint() {
            if (!this.role) {
                return false;
            }

            return !this.role.isNew() && this.mcpIntegrations.length > 0;
        },
    },

    watch: {
        roleId: {
            immediate: true,
            handler() {
                this.loadMcpIntegrations();
            },
        },
    },

    methods: {
        async loadMcpIntegrations() {
            if (!this.roleId) {
                this.mcpIntegrations = [];
                return;
            }

            const criteria = new Criteria(1, 500);
            const roleId = this.roleId;

            criteria.addFilter(Criteria.equals('admin', false));
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('mcpAllowlist', null)]));
            criteria.addFilter(Criteria.equals('aclRoles.id', roleId));
            criteria.addAssociation('aclRoles');

            try {
                const result = await this.integrationRepository.search(criteria);

                if (roleId !== this.roleId) {
                    return;
                }

                const elements = result.getElements ? Object.values(result.getElements()) : [...result];

                this.mcpIntegrations = elements.filter((integration) => {
                    const allowlist = integration.mcpAllowlist;

                    if (!allowlist) {
                        return false;
                    }

                    return (
                        Array.isArray(allowlist.tools) ||
                        Array.isArray(allowlist.resources) ||
                        Array.isArray(allowlist.prompts)
                    );
                });
            } catch {
                if (roleId !== this.roleId) {
                    return;
                }

                this.mcpIntegrations = [];
            }
        },

        onOpenMcpModal() {
            this.showMcpModal = true;
        },

        onCloseMcpModal() {
            this.showMcpModal = false;
        },
    },
};
