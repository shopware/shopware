/**
 * @sw-package framework
 */
import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { hasOwnProperty } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
        'userConfigService',
    ],

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
            hideSettingRenameBanner: true,
            searchQuery: '',
        };
    },

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    created() {
        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            this.getUserConfig();
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        settingsGroups() {
            // helpers
            const labelOfSetting = (setting) => (typeof setting.label === 'string' ? setting.label : setting.label?.label);

            const itemIsQueried = (str) => {
                const item = str.trim().toLowerCase();
                const query = this.searchQuery.trim().toLowerCase();
                if (query === '') {
                    return true;
                }
                return query.trim().includes(item) || item.includes(query);
            };

            /**
             * @param {(groupSettings: Setting[], groupName: string) => Setting[]} callback
             * @returns {(entry: [string, Setting[]]) => Setting[]}
             */
            const mapSettings =
                (callback) =>
                ([
                    name,
                    settings,
                ]) => [
                    name,
                    callback(settings, name),
                ];

            /**
             * @param {(groupSettings: Setting[], groupName: string) => boolean} callback
             * @returns {(entry: [string, Setting[]]) => boolean}
             */
            const filterGroup =
                (callback) =>
                ([
                    name,
                    settings,
                ]) =>
                    callback(settings, name);

            // Mappers
            const onlySearchResults = mapSettings((settings, groupName) => {
                // if group name is queried => full group
                if (itemIsQueried(this.getGroupLabel(groupName))) {
                    return settings;
                }

                // try match each settings label
                return settings.filter((setting) => itemIsQueried(this.getLabel(setting)));
            });

            const onlyPrivilegedSettings = mapSettings((settings) =>
                settings.filter((setting) => {
                    if (!setting.privilege) {
                        return true;
                    }
                    return this.acl.can(setting.privilege);
                }),
            );

            const sortSettings = mapSettings((settings) =>
                settings.sort((a, b) => {
                    const labelA = labelOfSetting(a);
                    const labelB = labelOfSetting(b);

                    return this.$tc(labelA).localeCompare(this.$tc(labelB));
                }),
            );

            // Filters
            const removeEmptyGroups = filterGroup((settings) => settings.length > 0);

            // Doing
            const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

            return Object.fromEntries(
                Object.entries(settingsGroups)
                    .map(onlyPrivilegedSettings)
                    .map(onlySearchResults)
                    .map(sortSettings)
                    .filter(removeEmptyGroups),
            );
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async getUserConfig() {
            const response = await this.userConfigService.search(['settings.hideRenameBanner']);
            this.hideSettingRenameBanner = !!response.data['settings.hideRenameBanner']?.value;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async onCloseSettingRenameBanner() {
            this.hideSettingRenameBanner = true;

            await this.userConfigService.upsert({
                'settings.hideRenameBanner': {
                    value: true,
                },
            });
        },

        hasPluginConfig() {
            return hasOwnProperty(this.settingsGroups, 'plugins') && this.settingsGroups.plugins.length > 0;
        },

        getRouteConfig(settingsItem) {
            if (!hasOwnProperty(settingsItem, 'to')) {
                return {};
            }

            if (typeof settingsItem.to === 'string') {
                return { name: settingsItem.to };
            }

            if (typeof settingsItem.to === 'object') {
                return settingsItem.to;
            }

            return {};
        },

        getLabel(settingsItem) {
            if (!hasOwnProperty(settingsItem, 'label')) {
                return '';
            }

            if (typeof settingsItem.label === 'string') {
                return this.$tc(settingsItem.label);
            }

            if (typeof settingsItem.label !== 'object') {
                return '';
            }

            if (!hasOwnProperty(settingsItem.label, 'translated')) {
                return '';
            }

            if (!hasOwnProperty(settingsItem.label, 'label') || typeof settingsItem.label.label !== 'string') {
                return '';
            }

            if (settingsItem.label.translated) {
                return settingsItem.label.label;
            }

            return this.$tc(settingsItem.label.label);
        },

        getGroupLabel(settingsGroup) {
            const upper = settingsGroup.charAt(0).toUpperCase() + settingsGroup.slice(1);
            return this.$tc(`sw-settings.index.tab${upper}`);
        },
    },
};
