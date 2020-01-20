import template from './sw-settings-logging-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-logging-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('sw-settings-list'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            entityName: 'log_entry',
            sortBy: 'log_entry.createdAt',
            sortDirection: 'DESC',
            isLoading: true,
            logs: [],
            displayedLog: null,
            logLevels: {
                Debug: 100,
                Info: 200,
                Notice: 250,
                Warning: 300,
                Error: 400,
                Critical: 500,
                Alert: 550,
                Emergency: 600
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {

        logEntryRepository() {
            return this.repositoryFactory.create('log_entry');
        },

        logColumns() {
            return this.getLogColumns();
        },

        modalNameFromLogEntry() {
            const eventName = this.displayedLog.message;

            const subComponentName = eventName.replace(/[._]/g, '-');
            if (this.$options.components[`sw-settings-logging-${subComponentName}-info`]) {
                return `sw-settings-logging-${subComponentName}-info`;
            }
            return 'sw-settings-logging-entry-info';
        }
    },

    methods: {
        showInfoModal(entryContents) {
            this.displayedLog = entryContents;
        },

        closeInfoModal() {
            this.displayedLog = null;
        },

        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return this.logEntryRepository.search(criteria, Shopware.Context.api).then((response) => {
                this.total = response.total;
                this.logs = response;
                this.isLoading = false;

                return response;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        logLevelToString(level) {
            const distances = Object.values(this.logLevels).map((x) => {
                return Math.abs(x - level);
            });

            const stringLevel = Object.keys(this.logLevels)[distances.findIndex((x) => {
                return x === Math.min(...distances);
            })];

            return this.$tc(`sw-settings-logging.list.level${stringLevel}`);
        },

        getLogColumns() {
            return [{
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: 'sw-settings-logging.list.columnDate',
                allowResize: true,
                primary: true
            }, {
                property: 'message',
                dataIndex: 'message',
                label: 'sw-settings-logging.list.columnMessage',
                allowResize: true
            }, {
                property: 'level',
                dataIndex: 'level',
                label: 'sw-settings-logging.list.columnLevel',
                allowResize: true
            }, {
                property: 'context',
                dataIndex: 'context',
                label: 'sw-settings-logging.list.columnContent',
                allowResize: true
            }];
        }
    }
});
