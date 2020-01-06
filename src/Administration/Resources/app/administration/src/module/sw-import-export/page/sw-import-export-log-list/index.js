import template from './sw-import-export-log-list.html.twig';
import Criteria from '../../../../core/data-new/criteria.data';
import './sw-import-export-log-list.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-import-export-log-list', {
    template,

    inject: ['importExportService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            logItems: null,
            repository: null,
            isLoading: false,
            selectedItem: null,
            total: null
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.repository = this.repositoryFactory.create('import_export_log');

            this.repository
                .search(new Criteria(), Shopware.Context.api)
                .then((response) => {
                    this.logItems = response;
                    this.isLoading = false;
                });
        },

        translateFieldKey(field, key) {
            return this.$tc(`sw-import-export-log.general.enum.${field}.${key}`);
        },

        getColumns() {
            return [{
                property: 'activity',
                dataIndex: 'activity',
                label: 'sw-import-export-log.list.columns.activity',
                routerLink: 'sw.import.export.profile_detail',
                allowResize: true,
                primary: true
            }, {
                property: 'profileName',
                dataIndex: 'profileName',
                label: 'sw-import-export-log.list.columns.profile',
                allowResize: true
            }, {
                property: 'state',
                dataIndex: 'state',
                label: 'sw-import-export-log.list.columns.state',
                allowResize: true
            }, {
                property: 'updatedAt',
                dataIndex: 'updatedAt',
                label: 'sw-import-export-log.list.columns.updatedAt',
                allowResize: true
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: 'sw-import-export-log.list.columns.createdAt',
                allowResize: true
            }, {
                property: 'username',
                dataIndex: 'username',
                label: 'sw-import-export-log.list.columns.user',
                allowResize: true
            }];
        },

        getDownloadUrl(file) {
            return this.importExportService.getDownloadUrl(file.id, file.accessToken);
        },

        triggerReload() {
            window.setTimeout(this.createdComponent(), 100);
        }
    }
});
