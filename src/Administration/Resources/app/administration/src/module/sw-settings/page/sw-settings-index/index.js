import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { Component } = Shopware;
const { hasOwnProperty } = Shopware.Utils.object;

Component.register('sw-settings-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        settingsGroups() {
            const settingsGroups = Object.entries(Shopware.State.get('settingsItems').settingsGroups);
            return settingsGroups.reduce((acc, [groupName, groupSettings]) => {
                acc[groupName] = groupSettings.sort(
                    (a, b) => (this.$tc(a.label).localeCompare(this.$tc(b.label)))
                );

                return acc;
            }, {});
        },

        /*
           @deprecated tag:v6.4.0
           we do not need to distinguish between plugin and default setting groups then anymore
           see ./sw-settings-index.html.twig
        */
        defaultSettingsGroups() {
            const settingsGroups = this.settingsGroups;
            return {
                shop: settingsGroups.shop,
                system: settingsGroups.system
            };
        },

        /*
            @deprecated tag:v6.4.0
            see above
         */
        pluginSettingsGroup() {
            const settingsGroups = this.settingsGroups;
            return hasOwnProperty(settingsGroups, 'plugins') ? settingsGroups.plugins : [];
        }
    },

    methods: {
        hasPluginConfig() {
            return (hasOwnProperty(this.settingsGroups, 'plugins') && this.settingsGroups.plugins.length > 0)
                // @deprecated tag:v6.4.0
                || (this.$refs.pluginConfig && this.$refs.pluginConfig.childElementCount > 0);
        }
    }
});
