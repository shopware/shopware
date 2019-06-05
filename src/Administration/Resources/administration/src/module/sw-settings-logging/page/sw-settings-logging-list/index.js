import { State, Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-logging-list.html.twig';
import './sw-settings-logging-list.scss';

Component.register('sw-settings-logging-list', {
    template,

    inject: ['loggingService'],

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'log_entry',
            sortBy: 'log_entry.createdAt',
            sortDirection: 'DSC',
            isLoading: true,
            logs: [],
            displayedLog: null
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
        },

        logInfoModalComponent() {
            const eventName = this.displayedLog.message;

            const subComponentName = eventName.replace(/[._]/g, '-');
            if (this.$options.components[`sw-settings-logging-${subComponentName}-info`]) {
                return `sw-settings-logging-${subComponentName}-info`;
            }
            return 'sw-settings-logging-entry-info';
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            this.logs = [];

            const params = this.getListingParams();
            console.log(params);
            return this.logEntryStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.logs = response.items;

                this.isLoading = false;

                return this.logs;
            });
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
                label: this.$tc('sw-settings-logging.list.columnMessage'),
                allowResize: true
            }, {
                property: 'level',
                dataIndex: 'level',
                label: this.$tc('sw-settings-logging.list.columnLevel'),
                allowResize: true
            }, {
                property: 'context',
                dataIndex: 'context',
                label: this.$tc('sw-settings-logging.list.columnContent'),
                allowResize: true
            }];
        },

        showInfoModal(entryContents) {
            this.displayedLog = entryContents;
        },

        closeInfoModal() {
            this.displayedLog = null;
        }

    }
});
