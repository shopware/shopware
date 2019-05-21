import { State, Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-logging-list.html.twig';
import './sw-settings-logging-list.scss';

Component.register('sw-settings-logging-list', {
    template,

    inject: ['loggingService'],
    // inject: ['logEntryStore'],

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'log_entry',
            sortBy: 'log_entry.createdAt',
            isLoading: true,
            logs: [],
            term: null,
            disableRouteParams: true
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        filters() {
            return [];
        },

        logColumns() {
            return this.getLogColumns();
        },

        logEntryStore() {
            return State.getStore('log_entry');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            this.logs = [];

            // this.loggingService.getLogs().then(response => {
            //     this.total = response.data.len;
            //     this.logs = response.data;
            //     this.isLoading = false;
            //
            //     return this.logs;
            // }
            const params = this.getListingParams();
            console.log(params);
            return this.logEntryStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.logs = response.items;

                this.isLoading = false;

                return this.logs;
            });

            // const params = this.getListingParams();
            // params.associations = {
            //     type: {},
            //     numberRangeSalesChannels: {
            //         associations: {
            //             salesChannel: {}
            //         }
            //     }
            // };
            //
            // return this.store.getList(params, true).then((response) => {
            //     this.total = response.total;
            //     this.items = response.items;
            //     this.isLoading = false;
            //
            //     return this.items;
            // });
        },

        getLogColumns() {
            return [{
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('sw-settings-logging.list.columnDate'),
                allowResize: true,
                primary: true
            }, {
                property: 'message',
                dataIndex: 'message',
                label: this.$tc('sw-settings-logging.list.message'),
                allowResize: true
            }, {
                property: 'level',
                dataIndex: 'level',
                label: this.$tc('sw-settings-logging.list.level'),
                allowResize: true
            }, {
                property: 'content',
                dataIndex: 'content',
                label: this.$tc('sw-settings-logging.list.columnContent'),
                allowResize: true
            }];
        },

        onSearch(searchTerm) {
            this.term = searchTerm;

            this.page = 1;
            this.getList();
        }

    }
});
