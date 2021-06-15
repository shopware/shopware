import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { Component } = Shopware;
const { hasOwnProperty } = Shopware.Utils.object;

Component.register('sw-settings-index', {
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
                    .filter(setting => this.acl.can(setting.privilege))
                    .sort((a, b) => (this.$tc(a.label).localeCompare(this.$tc(b.label))));

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
    },
});
