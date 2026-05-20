/**
 * @sw-package fundamentals@framework
 */
import './sw-users-permissions-role-view-general.scss';
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
            required: true,
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

        integrationRepository() {
            return this.repositoryFactory.create('integration');
        },

        isReadOnly() {
            return !this.acl.can('users_and_permissions.editor') || undefined;
        },

        shouldShowMcpHint() {
            return !this.role.isNew() && this.mcpIntegrations.length > 0;
        },
    },

    created() {
        if (!this.role.isNew()) {
            this.loadMcpIntegrations();
        }
    },

    methods: {
        loadMcpIntegrations() {
            const criteria = new Criteria(1, 500);

            criteria.addFilter(Criteria.equals('admin', false));
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('mcpAllowlist', null)]));
            criteria.addFilter(Criteria.equals('aclRoles.id', this.role.id));
            criteria.addAssociation('aclRoles');

            this.integrationRepository
                .search(criteria)
                .then((result) => {
                    const elements = result.getElements ? Object.values(result.getElements()) : [...result];
                    this.mcpIntegrations = elements.filter((i) => {
                        const allowlist = i.mcpAllowlist;
                        if (!allowlist) return false;
                        return (
                            Array.isArray(allowlist.tools) ||
                            Array.isArray(allowlist.resources) ||
                            Array.isArray(allowlist.prompts)
                        );
                    });
                })
                .catch(() => {
                    this.mcpIntegrations = [];
                });
        },

        onOpenMcpModal() {
            this.showMcpModal = true;
        },

        onCloseMcpModal() {
            this.showMcpModal = false;
        },
    },
};
