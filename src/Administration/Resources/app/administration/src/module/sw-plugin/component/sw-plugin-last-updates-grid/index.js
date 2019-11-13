import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-plugin-last-updates-grid.html.twig';
import './sw-plugin-last-updates-grid.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-plugin-last-updates-grid', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('plugin-error-handler')
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
            lastUpdates: [],
            isLoading: false,
            disableRouteParams: false
        };
    },

    computed: {
        pluginsStore() {
            return StateDeprecated.getStore('plugin');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    methods: {
        createdComponent() {
            this.$root.$on('updates-refresh', (total) => {
                if (total) {
                    return;
                }
                this.getList();
            });
        },

        getList() {
            this.isLoading = true;

            const params = this.getListingParams();

            this.pluginsStore.getList(params).then((response) => {
                this.lastUpdates = response.items;
                this.total = response.total;
                this.isLoading = false;
            }).catch((exception) => {
                this.handleErrorResponse(exception);
                this.isLoading = false;
            });
        },

        getListingParams() {
            const filterDate = new Date();
            filterDate.setDate(filterDate.getDate() - 7);

            return {
                limit: this.limit,
                sortBy: 'upgradedAt',
                sortDirection: 'DESC',
                criteria: CriteriaFactory.range('upgradedAt', {
                    gte: filterDate
                })
            };
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
