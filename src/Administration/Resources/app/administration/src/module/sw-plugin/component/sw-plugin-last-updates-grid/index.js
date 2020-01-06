import template from './sw-plugin-last-updates-grid.html.twig';
import './sw-plugin-last-updates-grid.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;


Component.register('sw-plugin-last-updates-grid', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            limit: 25,
            total: 0,
            page: 1,
            lastUpdates: [],
            isLoading: false,
            disableRouteParams: false
        };
    },

    computed: {
        pluginRepository() {
            return Shopware.Service('repositoryFactory').create('plugin');
        },

        pluginCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addFilter(Criteria.not(
                'AND',
                [
                    Criteria.equals('plugin.changelog', null),
                    Criteria.range('upgradedAt', { lt: this.filterDate })
                ]
            ));

            return criteria;
        },

        filterDate() {
            const date = new Date();
            date.setDate(date.getDate() - 7);

            return date;
        },

        context() {
            return Shopware.Context.api;
        },

        lastUpdatesColumns() {
            return [{
                property: 'name',
                label: 'sw-plugin.list.columnPlugin',
                align: 'center',
                width: 'auto'
            }, {
                property: 'changelog',
                label: 'sw-plugin.list.columnChangelog'

            }, {
                property: 'version',
                width: 'auto',
                align: 'center'
            }];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.getList().finally(() => {
                this.isLoading = false;
            });
        },

        getList() {
            this.isLoading = true;

            return this.pluginRepository.search(this.pluginCriteria, this.context).then((searchresult) => {
                this.lastUpdates = searchresult;
                this.total = searchresult.total;
                this.isLoading = false;
            });
        },

        getLatestChangelog(plugin) {
            if (plugin.changelog === null) {
                return '';
            }
            const json = JSON.stringify(plugin.changelog);
            const changelogs = JSON.parse(json);

            const latestChangelog = Object.entries(changelogs).pop();

            const changes = [];
            Object.values(latestChangelog).forEach((entry) => {
                changes.push(entry);
            });
            return changes[1];
        }
    }
});
