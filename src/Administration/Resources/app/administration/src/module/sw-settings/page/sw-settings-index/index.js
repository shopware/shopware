/**
 * @package system-settings
 */
import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { hasOwnProperty } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        settingsGroups() {
            const settingsGroups = Object.entries(Shopware.State.get('settingsItems').settingsGroups);
            return settingsGroups.reduce((acc, [groupName, groupSettings]) => {
                const group = groupSettings
                    .filter((setting) => {
                        if (!setting.privilege) {
                            return true;
                        }

                        return this.acl.can(setting.privilege);
                    })
                    .sort((a, b) => {
                        const labelA = typeof a.label === 'string' ? a.label : a.label?.label;
                        const labelB = typeof b.label === 'string' ? b.label : b.label?.label;

                        return this.$tc(labelA).localeCompare(this.$tc(labelB));
                    });

                if (group.length > 0) {
                    acc[groupName] = group;
                }

                return acc;
            }, {});
        },
    },

    methods: {
        hasPluginConfig() {
            return (hasOwnProperty(this.settingsGroups, 'plugins') && this.settingsGroups.plugins.length > 0);
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
    },
};
