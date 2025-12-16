/**
 * @sw-package framework
 */
import { type SettingsItem } from 'src/app/store/settings-item.store';
import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { hasOwnProperty } = Shopware.Utils.object;

type SettingsItemHere = Omit<SettingsItem, 'label'> & {
    label?: string | { label: string; translated: boolean };
} & { privilege?: string };

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
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
            void this.getUserConfig();
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        settingsGroups() {
            // Helpers
            const labelOfSetting = (setting: SettingsItemHere) =>
                typeof setting.label === 'string' ? setting.label : (setting.label?.label ?? '');

            const mapSettings =
                (
                    mapper: (settings: SettingsItemHere[], groupName: string) => SettingsItemHere[],
                ): ((entry: [string, SettingsItemHere[]]) => [string, SettingsItemHere[]]) =>
                ([
                    name,
                    settings,
                ]) => [
                    name,
                    mapper(settings, name),
                ];

            const filterGroup =
                (
                    predicate: (settings: SettingsItemHere[], groupName: string) => boolean,
                ): ((entry: [string, SettingsItemHere[]]) => boolean) =>
                ([
                    name,
                    settings,
                ]) =>
                    predicate(settings, name);

            // Mappers
            const onlySearchResults = mapSettings((settings, groupName) => {
                // if group name is queried => full group
                if (this.itemIsQueried(this.getGroupLabel(groupName))) {
                    return settings;
                }

                // try match each settings label
                return settings.filter((setting) => this.itemIsQueried(this.getLabel(setting)));
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

            // Doing: Transform the settings
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
            // @ts-expect-error - type error won't be fixed as it is deprecated anyway
            this.hideSettingRenameBanner = !!response?.data['settings.hideRenameBanner']?.value;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async onCloseSettingRenameBanner() {
            this.hideSettingRenameBanner = true;

            await this.userConfigService.upsert({
                'settings.hideRenameBanner': {
                    // @ts-expect-error - type error won't be fixed as it is deprecated anyway
                    value: true,
                },
            });
        },

        hasPluginConfig() {
            return hasOwnProperty(this.settingsGroups, 'plugins') && this.settingsGroups.plugins.length > 0;
        },

        getRouteConfig(settingsItem: SettingsItemHere) {
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

        getLabel(settingsItem: SettingsItemHere) {
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

        getGroupLabel(settingsGroup: string) {
            const upper = settingsGroup.charAt(0).toUpperCase() + settingsGroup.slice(1);
            return this.$tc(`sw-settings.index.tab${upper}`);
        },

        itemIsQueried(label: string) {
            const query = this.searchQuery.trim().toLowerCase();
            const item = label.trim().toLowerCase();
            if (query === '') {
                return true;
            }
            return item.includes(query) || query.includes(item);
        },
    },
});
