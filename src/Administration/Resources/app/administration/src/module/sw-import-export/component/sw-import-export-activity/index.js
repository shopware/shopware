import template from './sw-import-export-activity.html.twig';
import './sw-import-export-activity.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { format } = Shopware.Utils;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-activity', {
    template,

    inject: ['repositoryFactory', 'importExport', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        type: {
            type: String,
            required: false,
            default: 'import',
            validValues: [
                'import',
                'export',
            ],
            validator(value) {
                return [
                    'import',
                    'export',
                ].includes(value);
            },
        },
    },

    data() {
        return {
            logs: null,
            isLoading: false,
            selectedProfile: null,
            selectedLog: null,
            selectedResult: null,
        };
    },

    computed: {
        logRepository() {
            return this.repositoryFactory.create('import_export_log');
        },

        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },

        activityCriteria() {
            const criteria = new Shopware.Data.Criteria();

            if (this.type === 'import') {
                criteria.addFilter(Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('activity', 'import'),
                        Criteria.equals('activity', 'dryrun'),
                    ],
                ));
                criteria.addAssociation('invalidRecordsLog');
            } else if (this.type === 'export') {
                criteria.addFilter(Criteria.equals('activity', 'export'));
            }

            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            criteria.setPage(1);
            criteria.addAssociation('user');
            criteria.addAssociation('file');

            return criteria;
        },

        exportActivityColumns() {
            return [
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-import-export.activity.columns.date',
                    allowResize: true,
                    primary: true,
                }, {
                    property: 'profileName',
                    dataIndex: 'profileName',
                    label: 'sw-import-export.activity.columns.profile',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'records',
                    dataIndex: 'records',
                    label: 'sw-import-export.activity.columns.records',
                    allowResize: true,
                    primary: false,
                },
                ...(this.type === 'import' ? [{
                    property: 'invalidRecords',
                    dataIndex: 'records',
                    label: 'sw-import-export.activity.columns.invalidRecords',
                    allowResize: true,
                    primary: false,
                }] : []),
                {
                    property: 'file.size',
                    dataIndex: 'file.size',
                    label: 'sw-import-export.activity.columns.size',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'user.lastName',
                    dataIndex: 'user.lastName',
                    label: 'sw-import-export.activity.columns.user',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'state',
                    dataIndex: 'state',
                    label: 'sw-import-export.activity.columns.state',
                    allowResize: true,
                    primary: false,
                }];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            return this.fetchActivities();
        },

        async fetchActivities() {
            this.isLoading = true;

            this.logs = await this.logRepository.search(this.activityCriteria);

            this.isLoading = false;
        },

        async onOpenProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id);
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        onShowLog(item) {
            this.selectedLog = item;
        },

        onShowResult(result) {
            const items = [];

            Object.keys(result).forEach((entityName) => {
                items.push({ ...{ entityName }, ...result[entityName] });
            });

            this.selectedResult = items;
        },

        closeSelectedLog() {
            this.selectedLog = null;
        },

        closeSelectedResult() {
            this.selectedResult = null;
        },

        /**
         * @deprecated tag:v6.5.0 - Remove unused method, use openDownload instead
         */
        getDownloadUrl() {
            Shopware.Utils.debug.error('The method getDownloadUrl has been replaced with openDownload.');

            return '';
        },

        async openDownload(id) {
            return window.open(await this.importExport.getDownloadUrl(id), '_blank');
        },

        saveSelectedProfile() {
            this.isLoading = true;
            this.profileRepository.save(this.selectedProfile).then(() => {
                this.selectedProfile = null;
                this.createNotificationSuccess({
                    message: this.$tc('sw-import-export.profile.messageSaveSuccess', 0),
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-import-export.profile.messageSaveError', 0),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        calculateFileSize(size) {
            return format.fileSize(size);
        },

        getStateLabel(state) {
            const translationKey = `sw-import-export.activity.status.${state}`;

            return this.$te(translationKey) ? this.$tc(translationKey) : state;
        },
    },
});
