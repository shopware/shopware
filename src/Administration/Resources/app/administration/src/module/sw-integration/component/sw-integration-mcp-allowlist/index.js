/**
 * @sw-package fundamentals@framework
 */
import { computePrivilegeChips, isPrivilegeGranted } from 'src/core/helper/mcp-privilege.helper';
import { buildGroups, humanizeLabel, humanizeCommonPrefix } from './mcp-allowlist.utils';
import template from './sw-integration-mcp-allowlist.html.twig';
import './sw-integration-mcp-allowlist.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mcpToolService',
        'acl',
    ],

    props: {
        /**
         * null = all capabilities unrestricted (primary toggle on)
         * {tools, resources, prompts} = per-type allowlists (null per type = unrestricted)
         */
        allowlist: {
            type: Object,
            default: null,
        },

        disabled: {
            type: Boolean,
            default: false,
        },

        isAdmin: {
            type: Boolean,
            default: false,
        },

        grantedPrivileges: {
            type: Array,
            default: () => [],
        },
    },

    emits: ['update:allowlist'],

    data() {
        return {
            availableTools: [],
            availableResources: [],
            availablePrompts: [],
            isLoading: false,
            openTypes: new Set(),
            openGroups: new Set(),
            openItems: new Set(),
        };
    },

    computed: {
        allCapabilitiesEnabled: {
            get() {
                return this.allowlist === null;
            },
            set(enabled) {
                this.$emit('update:allowlist', enabled ? null : { tools: null, resources: null, prompts: null });
            },
        },

        toolsAllowlist() {
            if (this.allowlist === null) return null;
            return this.allowlist.tools ?? null;
        },

        resourcesAllowlist() {
            if (this.allowlist === null) return null;
            return this.allowlist.resources ?? null;
        },

        promptsAllowlist() {
            if (this.allowlist === null) return null;
            return this.allowlist.prompts ?? null;
        },

        toolGroups() {
            return buildGroups(this.availableTools, (tool) => tool.name.split('-')[0] ?? 'other');
        },

        resourceGroups() {
            return buildGroups(this.availableResources, (resource) => {
                const match = resource.uri.match(/^([a-zA-Z0-9_-]+):\/\//);
                return match ? match[1] : (resource.name?.split('-')[0] ?? 'other');
            });
        },

        promptGroups() {
            return buildGroups(this.availablePrompts, (prompt) => prompt.name.split('-')[0] ?? 'other');
        },

        typeConfigs() {
            return [
                {
                    key: 'tools',
                    titleKey: 'sw-integration.mcp.toolsSection',
                    guidanceKey: 'sw-integration.mcp.toolsGuidance',
                    available: this.availableTools,
                    groups: this.toolGroups,
                },
                {
                    key: 'resources',
                    titleKey: 'sw-integration.mcp.resourcesSection',
                    guidanceKey: 'sw-integration.mcp.resourcesGuidance',
                    available: this.availableResources,
                    groups: this.resourceGroups,
                },
                {
                    key: 'prompts',
                    titleKey: 'sw-integration.mcp.promptsSection',
                    guidanceKey: 'sw-integration.mcp.promptsGuidance',
                    available: this.availablePrompts,
                    groups: this.promptGroups,
                },
            ];
        },

        staleToolNames() {
            if (this.toolsAllowlist === null) return [];
            const available = this.availableTools.map((t) => t.name);
            return this.toolsAllowlist.filter((name) => !available.includes(name));
        },

        staleResourceUris() {
            if (this.resourcesAllowlist === null) return [];
            const available = this.availableResources.map((r) => r.uri);
            return this.resourcesAllowlist.filter((uri) => !available.includes(uri));
        },

        stalePromptNames() {
            if (this.promptsAllowlist === null) return [];
            const available = this.availablePrompts.map((p) => p.name);
            return this.promptsAllowlist.filter((name) => !available.includes(name));
        },

        staleEntries() {
            return [
                ...this.staleToolNames,
                ...this.staleResourceUris,
                ...this.stalePromptNames,
            ];
        },

        uncoveredTools() {
            if (this.isAdmin || this.grantedPrivileges.length === 0) {
                return [];
            }

            const toolsToCheck =
                this.toolsAllowlist === null
                    ? this.availableTools
                    : this.toolsAllowlist.map((name) => this.availableTools.find((t) => t.name === name)).filter(Boolean);

            return toolsToCheck.filter((tool) => this.missingPrivilegesForTool(tool.name).length > 0);
        },

        deniedTypes() {
            if (this.allCapabilitiesEnabled) return [];
            return this.typeConfigs
                .filter(
                    (tc) => !this.typeAllEnabled(tc.key) && this.typeSelectedCount(tc.key) === 0 && tc.available.length > 0,
                )
                .map((tc) => tc.key);
        },

        deniedTypesLabel() {
            const labels = this.deniedTypes.map((t) => this.$t(`sw-integration.mcp.${t}Section`));
            if (labels.length === 0) return '';
            if (labels.length === 1) return labels[0];
            return `${labels.slice(0, -1).join(', ')} and ${labels[labels.length - 1]}`;
        },

        missingCapabilitySuggestions() {
            if (this.allCapabilitiesEnabled) return [];

            const activeToolNames =
                this.toolsAllowlist === null ? this.availableTools.map((t) => t.name) : this.toolsAllowlist;

            const activePrefixes = new Set(activeToolNames.map((n) => n.split('-')[0]));
            const suggestions = [];

            // Only check partially-restricted types; fully-denied types are already covered by the deniedTypes banner
            if (this.resourcesAllowlist !== null && this.resourcesAllowlist.length > 0) {
                Object.entries(this.resourceGroups).forEach(
                    ([
                        prefix,
                        resources,
                    ]) => {
                        if (!activePrefixes.has(prefix)) return;
                        resources.forEach((resource) => {
                            if (!this.resourcesAllowlist.includes(resource.uri)) {
                                suggestions.push({ kind: 'resource', name: resource.uri });
                            }
                        });
                    },
                );
            }

            if (this.promptsAllowlist !== null && this.promptsAllowlist.length > 0) {
                Object.entries(this.promptGroups).forEach(
                    ([
                        prefix,
                        prompts,
                    ]) => {
                        if (!activePrefixes.has(prefix)) return;
                        prompts.forEach((prompt) => {
                            if (!this.promptsAllowlist.includes(prompt.name)) {
                                suggestions.push({ kind: 'prompt', name: prompt.name });
                            }
                        });
                    },
                );
            }

            return suggestions;
        },

        allMissingPrivileges() {
            const all = new Set();
            this.uncoveredTools.forEach((tool) => {
                this.missingPrivilegesForTool(tool.name).forEach((p) => all.add(p));
            });
            return [...all].sort();
        },
    },

    created() {
        this.loadCapabilities();
    },

    methods: {
        isPrivilegeGranted,

        loadCapabilities() {
            this.isLoading = true;

            this.mcpToolService
                .getCapabilities()
                .then((data) => {
                    this.availableTools = data.tools ?? [];
                    this.availableResources = data.resources ?? [];
                    this.availablePrompts = data.prompts ?? [];
                })
                .catch(() => {
                    this.availableTools = [];
                    this.availableResources = [];
                    this.availablePrompts = [];
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        emitUpdated(patch) {
            const current = this.allowlist ?? { tools: null, resources: null, prompts: null };
            this.$emit('update:allowlist', { ...current, ...patch });
        },

        isTypeExpanded(type) {
            return this.openTypes.has(type);
        },

        toggleType(type) {
            const next = new Set(this.openTypes);
            if (next.has(type)) {
                next.delete(type);
            } else {
                next.add(type);
            }
            this.openTypes = next;
        },

        isGroupExpanded(type, group) {
            return this.openGroups.has(`${type}:${group}`);
        },

        toggleGroup(type, group) {
            const key = `${type}:${group}`;
            const next = new Set(this.openGroups);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            this.openGroups = next;
        },

        expandAllGroups(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return;
            const next = new Set(this.openGroups);
            Object.keys(config.groups).forEach((group) => next.add(`${type}:${group}`));
            this.openGroups = next;
        },

        collapseAllGroups(type) {
            const next = new Set([...this.openGroups].filter((k) => !k.startsWith(`${type}:`)));
            this.openGroups = next;
        },

        areAllGroupsExpanded(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return false;
            const keys = Object.keys(config.groups);
            return keys.length > 0 && keys.every((group) => this.isGroupExpanded(type, group));
        },

        isItemExpanded(type, item) {
            return this.openItems.has(`${type}:${this.itemKey(type, item)}`);
        },

        toggleItem(type, item) {
            const key = `${type}:${this.itemKey(type, item)}`;
            const next = new Set(this.openItems);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            this.openItems = next;
        },

        expandAllItemsInGroup(type, group) {
            const next = new Set(this.openItems);
            this.getGroupItems(type, group)
                .filter((item) => !!item.description)
                .forEach((item) => next.add(`${type}:${this.itemKey(type, item)}`));
            this.openItems = next;
        },

        collapseAllItemsInGroup(type, group) {
            const keys = new Set(this.getGroupItems(type, group).map((item) => `${type}:${this.itemKey(type, item)}`));
            this.openItems = new Set([...this.openItems].filter((k) => !keys.has(k)));
        },

        areAllItemsInGroupExpanded(type, group) {
            const expandable = this.getGroupItems(type, group).filter((item) => !!item.description);
            return expandable.length > 0 && expandable.every((item) => this.isItemExpanded(type, item));
        },

        onToggleTypeAll(type, enabled) {
            this.emitUpdated({ [type]: enabled ? null : [] });
        },

        // Tools
        isToolSelected(toolName) {
            if (this.toolsAllowlist === null) return true;
            return this.toolsAllowlist.includes(toolName);
        },

        onToggleTool(toolName, isSelected) {
            const current = this.toolsAllowlist ?? [];
            let updated;

            if (isSelected) {
                const tool = this.availableTools.find((t) => t.name === toolName);
                const deps = tool?.dependencies ?? [];
                const toAdd = [
                    toolName,
                    ...deps,
                ].filter((n) => !current.includes(n));
                updated = [
                    ...current,
                    ...toAdd,
                ];
            } else {
                updated = current.filter((n) => n !== toolName);
            }

            this.emitUpdated({ tools: updated });
        },

        isDependency(toolName) {
            if (this.toolsAllowlist === null) return false;
            return this.toolsAllowlist.some((selected) => {
                const tool = this.availableTools.find((t) => t.name === selected);
                return tool?.dependencies?.includes(toolName) ?? false;
            });
        },

        missingDependencies(tool) {
            if (!this.isToolSelected(tool.name)) return [];
            return (tool.dependencies ?? []).filter((dep) => !this.isToolSelected(dep));
        },

        privilegeChipClass(chip) {
            if (this.isAdmin || this.grantedPrivileges.length === 0 || chip.startsWith('<')) {
                return '';
            }
            return isPrivilegeGranted(chip, this.grantedPrivileges) ? 'is--granted' : 'is--missing';
        },

        privilegeChips(tool) {
            return computePrivilegeChips(tool.requiredPrivileges);
        },

        missingPrivilegesForTool(toolName) {
            const tool = this.availableTools.find((t) => t.name === toolName);
            const reqs = tool?.requiredPrivileges;
            if (!reqs?.static?.length) return [];
            return reqs.static.filter((priv) => !isPrivilegeGranted(priv, this.grantedPrivileges));
        },

        itemKey(type, item) {
            return type === 'resources' ? item.uri : item.name;
        },

        onToggleItem(type, item, selected) {
            if (type === 'tools') this.onToggleTool(item.name, selected);
            else if (type === 'resources') this.onToggleResource(item.uri, selected);
            else if (type === 'prompts') this.onTogglePrompt(item.name, selected);
        },

        // Resources
        isResourceSelected(uri) {
            if (this.resourcesAllowlist === null) return true;
            return this.resourcesAllowlist.includes(uri);
        },

        onToggleResource(uri, isSelected) {
            const current = this.resourcesAllowlist ?? [];
            const updated = isSelected
                ? [
                      ...current,
                      uri,
                  ]
                : current.filter((u) => u !== uri);
            this.emitUpdated({ resources: updated });
        },

        // Prompts
        isPromptSelected(name) {
            if (this.promptsAllowlist === null) return true;
            return this.promptsAllowlist.includes(name);
        },

        onTogglePrompt(name, isSelected) {
            const current = this.promptsAllowlist ?? [];
            const updated = isSelected
                ? [
                      ...current,
                      name,
                  ]
                : current.filter((n) => n !== name);
            this.emitUpdated({ prompts: updated });
        },

        // Group operations
        getGroupItems(type, group) {
            const maps = {
                tools: this.toolGroups,
                resources: this.resourceGroups,
                prompts: this.promptGroups,
            };
            return maps[type]?.[group] ?? [];
        },

        isItemSelectedForGroup(type, item) {
            if (type === 'tools') return this.isToolSelected(item.name);
            if (type === 'resources') return this.isResourceSelected(item.uri);
            if (type === 'prompts') return this.isPromptSelected(item.name);
            return false;
        },

        isGroupAllSelected(type, group) {
            const items = this.getGroupItems(type, group);
            return items.length > 0 && items.every((item) => this.isItemSelectedForGroup(type, item));
        },

        isGroupPartiallySelected(type, group) {
            const items = this.getGroupItems(type, group);
            const count = items.filter((item) => this.isItemSelectedForGroup(type, item)).length;
            return count > 0 && count < items.length;
        },

        onToggleGroupAll(type, group, checked) {
            const items = this.getGroupItems(type, group);

            if (type === 'tools') {
                const current = this.toolsAllowlist ?? [];
                const names = items.map((t) => t.name);
                if (checked) {
                    const deps = names.flatMap((n) => {
                        const tool = this.availableTools.find((t) => t.name === n);
                        return tool?.dependencies ?? [];
                    });
                    const toAdd = [
                        ...names,
                        ...deps,
                    ].filter((n) => !current.includes(n));
                    this.emitUpdated({
                        tools: [
                            ...current,
                            ...toAdd,
                        ],
                    });
                } else {
                    this.emitUpdated({ tools: current.filter((n) => !names.includes(n)) });
                }
                return;
            }

            if (type === 'resources') {
                const current = this.resourcesAllowlist ?? [];
                const uris = items.map((r) => r.uri);
                const updated = checked
                    ? [
                          ...current,
                          ...uris.filter((u) => !current.includes(u)),
                      ]
                    : current.filter((u) => !uris.includes(u));
                this.emitUpdated({ resources: updated });
                return;
            }

            if (type === 'prompts') {
                const current = this.promptsAllowlist ?? [];
                const names = items.map((p) => p.name);
                const updated = checked
                    ? [
                          ...current,
                          ...names.filter((n) => !current.includes(n)),
                      ]
                    : current.filter((n) => !names.includes(n));
                this.emitUpdated({ prompts: updated });
            }
        },

        groupLabel(type, group) {
            if (type === 'resources') {
                return humanizeLabel(group);
            }

            const items = this.getGroupItems(type, group);
            return humanizeCommonPrefix(items.map((item) => item.name));
        },

        typeSelectedCount(type) {
            if (type === 'tools') {
                if (this.toolsAllowlist === null) return this.availableTools.length;
                return this.toolsAllowlist.filter((n) => this.availableTools.some((t) => t.name === n)).length;
            }
            if (type === 'resources') {
                if (this.resourcesAllowlist === null) return this.availableResources.length;
                return this.resourcesAllowlist.filter((u) => this.availableResources.some((r) => r.uri === u)).length;
            }
            if (type === 'prompts') {
                if (this.promptsAllowlist === null) return this.availablePrompts.length;
                return this.promptsAllowlist.filter((n) => this.availablePrompts.some((p) => p.name === n)).length;
            }
            return 0;
        },

        isFlatType(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return false;
            const groupKeys = Object.keys(config.groups);
            if (groupKeys.length === 0) return false;
            // Single group, or every group has exactly one item
            return groupKeys.length === 1 || groupKeys.every((g) => (config.groups[g]?.length ?? 0) === 1);
        },

        flatTypeFirstGroupKey(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return null;
            const keys = Object.keys(config.groups);
            return keys.length > 0 ? keys[0] : null;
        },

        flatTypeHasExpandableItems(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return false;
            return Object.keys(config.groups).some(
                (g) => this.getGroupItems(type, g).filter((i) => !!i.description).length > 0,
            );
        },

        expandAllItemsInType(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return;
            Object.keys(config.groups).forEach((g) => this.expandAllItemsInGroup(type, g));
        },

        collapseAllItemsInType(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return;
            Object.keys(config.groups).forEach((g) => this.collapseAllItemsInGroup(type, g));
        },

        areAllItemsInTypeExpanded(type) {
            const config = this.typeConfigs.find((c) => c.key === type);
            if (!config) return false;
            const expandable = Object.keys(config.groups).flatMap((g) =>
                this.getGroupItems(type, g).filter((i) => !!i.description),
            );
            return expandable.length > 0 && expandable.every((item) => this.isItemExpanded(type, item));
        },

        typeTotal(type) {
            if (type === 'tools') return this.availableTools.length;
            if (type === 'resources') return this.availableResources.length;
            if (type === 'prompts') return this.availablePrompts.length;
            return 0;
        },

        typeAllEnabled(type) {
            if (type === 'tools') return this.toolsAllowlist === null;
            if (type === 'resources') return this.resourcesAllowlist === null;
            if (type === 'prompts') return this.promptsAllowlist === null;
            return true;
        },
    },
};
