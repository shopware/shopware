/**
 * @sw-package framework
 *
 * Dummy override using Options API to test the Options-Composition API shim.
 * This override targets 'sw-settings-index' which now uses Composition API
 * via createExtendableSetup(). The shim should transparently convert this
 * Options API override to work with the Composition API component.
 *
 * Scenarios tested:
 * - data: new reactive properties added via Options API data()
 * - computed: new computed property accessing previous state
 * - methods: override existing method with $super delegation
 * - methods: add new methods accessing both local and previous state
 * - watch: watch a reactive property from the Composition API component
 * - lifecycle hooks: created and mounted fire at the correct time
 */

/* eslint-disable no-console, sw-deprecation-rules/private-feature-declarations */

Shopware.Component.override('sw-settings-index', {
    created() {
        console.log('[Options API Shim Test] created hook fired — searchQuery:', this.searchQuery);
    },

    mounted() {
        console.log('[Options API Shim Test] mounted hook fired — total settings groups:', Object.keys(this.settingsGroups).length);
    },

    data() {
        return {
            overrideMessage: 'Hello from Options API override!',
            searchCount: 0,
        };
    },

    computed: {
        settingsGroups() {
            const original = this.$super('settingsGroups');

            console.log('[Options API Shim Test] settingsGroups $super returned', Object.keys(original).length, 'groups');

            return {
                ...original,
                plugin: [
                    {
                        id: 'sw-settings-plugin-example-1',
                        name: 'plugin-example-config',
                        group: 'plugin',
                        to: 'sw.settings.index.shop',
                        icon: 'regular-plug',
                        label: { label: 'My Plugin Config', translated: true },
                    },
                    {
                        id: 'sw-settings-plugin-example-2',
                        name: 'plugin-example-feature',
                        group: 'plugin',
                        to: 'sw.settings.index.shop',
                        icon: 'regular-star',
                        label: { label: 'Plugin Feature Flags', translated: true },
                    },
                ],
            };
        },

        totalSettingsCount() {
            const groups = this.settingsGroups;
            if (!groups || typeof groups !== 'object') return 0;
            return Object.values(groups).reduce((total, items) => total + items.length, 0);
        },
    },

    methods: {
        getGroupLabel(settingsGroup) {
            if (settingsGroup === 'plugin') {
                console.log('[Options API Shim Test] getGroupLabel returning custom label for plugin group');
                return 'From plugin';
            }

            const originalLabel = this.$super('getGroupLabel', settingsGroup);
            console.log('[Options API Shim Test] getGroupLabel called with $super:', settingsGroup, '->', originalLabel);
            return originalLabel;
        },

        itemIsQueried(label) {
            const result = this.$super('itemIsQueried', label);
            console.log('[Options API Shim Test] itemIsQueried delegated to $super:', label, '->', result);
            return result;
        },

        getOverrideInfo() {
            return `${this.overrideMessage} | Total settings: ${this.totalSettingsCount} | Searches: ${this.searchCount}`;
        },
    },

    watch: {
        searchQuery(newVal, oldVal) {
            console.log('[Options API Shim Test] searchQuery changed:', oldVal, '->', newVal);
            this.searchCount = this.searchCount + 1;
        },
    },
});
