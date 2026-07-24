/**
 * @sw-package fundamentals@framework
 */
import { colonToDot, computePrivilegeChips, isPrivilegeGranted } from 'src/core/helper/mcp-privilege.helper';
import './sw-users-permissions-role-mcp-reference-modal.scss';
import template from './sw-users-permissions-role-mcp-reference-modal.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mcpToolService',
    ],

    props: {
        role: {
            type: Object,
            required: true,
        },

        mcpIntegrations: {
            type: Array,
            default: () => [],
        },
    },

    emits: ['modal-close'],

    data() {
        return {
            availableTools: [],
            isLoading: false,
            viewMode: 'permission',
            hasPreselected: false,
        };
    },

    computed: {
        filterOptions() {
            return [
                {
                    value: 'permission',
                    label: this.$t('sw-users-permissions.roles.mcpModal.viewByPermission'),
                },
                {
                    value: 'tool',
                    label: this.$t('sw-users-permissions.roles.mcpModal.viewByTool'),
                },
            ];
        },

        anyIntegrationAllowsAllTools() {
            return this.mcpIntegrations.some((integration) => {
                const tools = integration.mcpAllowlist?.tools;
                return tools === null || tools === undefined;
            });
        },

        allowlistedToolNames() {
            if (this.anyIntegrationAllowsAllTools) {
                return this.availableTools.map((tool) => tool.name);
            }

            const names = new Set();

            this.mcpIntegrations.forEach((integration) => {
                (integration.mcpAllowlist?.tools ?? []).forEach((name) => names.add(name));
            });

            return [...names].sort();
        },

        relevantTools() {
            return this.availableTools
                .filter((tool) => {
                    if (!this.allowlistedToolNames.includes(tool.name)) {
                        return false;
                    }
                    const reqs = tool.requiredPrivileges;
                    return (reqs?.static?.length ?? 0) > 0 || reqs?.entityParam != null;
                })
                .map((tool) => {
                    const reqs = tool.requiredPrivileges;
                    return {
                        ...tool,
                        staticPrivileges: reqs?.static ?? [],
                        dynamicPrivileges: computePrivilegeChips(reqs).filter((c) => c.startsWith('<')),
                    };
                });
        },

        rolePrivileges() {
            return this.role.privileges ?? [];
        },

        displayRows() {
            if (this.viewMode === 'permission') {
                const map = {};

                this.relevantTools.forEach((tool) => {
                    [
                        ...tool.staticPrivileges,
                        ...tool.dynamicPrivileges,
                    ].forEach((chip) => {
                        const isDynamic = chip.startsWith('<');
                        const entity = isDynamic ? '<entity>' : chip.split(':')[0];

                        if (!map[entity]) {
                            map[entity] = { chips: [] };
                        }

                        if (!map[entity].chips.find((c) => c.text === chip)) {
                            map[entity].chips.push({
                                text: chip,
                                isDynamic,
                                isGranted: !isDynamic && isPrivilegeGranted(chip, this.rolePrivileges),
                            });
                        }
                    });
                });

                return Object.entries(map)
                    .map(
                        ([
                            label,
                            { chips },
                        ]) => ({
                            label,
                            chips,
                            hasMissingStatic: chips.some((c) => !c.isDynamic && !c.isGranted),
                        }),
                    )
                    .sort((a, b) => a.label.localeCompare(b.label));
            }

            return this.relevantTools
                .map((tool) => ({
                    label: tool.name,
                    chips: [
                        ...tool.staticPrivileges.map((chip) => ({
                            text: chip,
                            isDynamic: false,
                            isGranted: isPrivilegeGranted(chip, this.rolePrivileges),
                        })),
                        ...tool.dynamicPrivileges.map((chip) => ({
                            text: chip,
                            isDynamic: true,
                            isGranted: false,
                        })),
                    ],
                    hasMissingStatic: tool.staticPrivileges.some((chip) => !isPrivilegeGranted(chip, this.rolePrivileges)),
                }))
                .sort((a, b) => a.label.localeCompare(b.label));
        },

        allMissingStatic() {
            const missing = new Set();

            this.relevantTools.forEach((tool) => {
                tool.staticPrivileges.forEach((chip) => {
                    if (!isPrivilegeGranted(chip, this.rolePrivileges)) {
                        missing.add(chip);
                    }
                });
            });

            return [...missing];
        },
    },

    created() {
        this.loadTools();
    },

    methods: {
        getBadgeVariant(chip) {
            if (!chip.isDynamic && chip.isGranted) {
                return 'positive';
            }

            if (!chip.isDynamic && !chip.isGranted) {
                return 'critical';
            }

            return 'neutral';
        },

        loadTools() {
            this.isLoading = true;

            this.mcpToolService
                .getTools()
                .then((tools) => {
                    this.availableTools = tools;
                })
                .catch(() => {
                    this.availableTools = [];
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        grantPrivilege(chip) {
            const dotPriv = colonToDot(chip);
            if (!dotPriv) return;

            if (!this.role.privileges.includes(dotPriv)) {
                this.role.privileges.push(dotPriv);
            }

            const [
                entity,
                rolePart,
            ] = dotPriv.split('.');
            if (
                [
                    'editor',
                    'creator',
                    'deleter',
                ].includes(rolePart)
            ) {
                const viewerPriv = `${entity}.viewer`;
                if (!this.role.privileges.includes(viewerPriv)) {
                    this.role.privileges.push(viewerPriv);
                }
            }

            this.hasPreselected = true;
        },

        grantAllMissing() {
            this.allMissingStatic.forEach((chip) => this.grantPrivilege(chip));
        },

        grantRow(row) {
            row.chips.filter((c) => !c.isDynamic && !c.isGranted).forEach((c) => this.grantPrivilege(c.text));
        },

        closeModal() {
            this.hasPreselected = false;
            this.$emit('modal-close');
        },
    },
};
