/**
 * @package services-settings
 */
import template from './sw-import-export-activity.html.twig';
import './sw-import-export-activity.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { format } = Shopware.Utils;

/**
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'importExport',
        'feature',
    ],

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
            logs: new EntityCollection('/import-export-log', 'import_export_log', null),
            isLoading: false,
            selectedProfile: null,
            selectedLog: null,
            selectedResult: null,
            activitiesReloadIntervall: 10000,
            activitiesReloadTimer: null,
            showDetailModal: false,
            showResultModal: false,
            stateText: {
                import: {
                    succeeded: 'sw-import-export.importer.messageImportSuccess',
                    failed: 'sw-import-export.importer.messageImportError',
                },
                dryrun: {
                    succeeded: 'sw-import-export.importer.messageImportSuccess',
                    failed: 'sw-import-export.importer.messageImportError',
                },
                export: {
                    succeeded: 'sw-import-export.exporter.messageExportSuccess',
                    failed: 'sw-import-export.exporter.messageExportError',
                },
            },
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
                criteria.addFilter(
                    Criteria.multi('OR', [
                        Criteria.equals('activity', 'import'),
                        Criteria.equals('activity', 'dryrun'),
                    ]),
                );
            } else if (this.type === 'export') {
                criteria.addFilter(Criteria.equals('activity', 'export'));
            }

            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            criteria.setPage(1);
            criteria.addAssociation('user');
            criteria.addAssociation('file');
            criteria.addAssociation('profile');
            criteria.getAssociation('invalidRecordsLog').addAssociation('file');

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
                },
                {
                    property: 'profileName',
                    dataIndex: 'profile.label',
                    label: 'sw-import-export.activity.columns.profile',
                    allowResize: true,
                    primary: false,
                },
                {
                    property: 'state',
                    dataIndex: 'state',
                    label: 'sw-import-export.activity.columns.state',
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
                ...(this.type === 'import'
                    ? [
                          {
                              property: 'invalidRecords',
                              dataIndex: 'records',
                              label: 'sw-import-export.activity.columns.invalidRecords',
                              allowResize: true,
                              primary: false,
                          },
                      ]
                    : []),
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
            ];
        },

        hasActivitiesInProgress() {
            return this.logs.filter((log) => log.state === 'progress').length > 0;
        },

        downloadFileText() {
            return this.type === 'export'
                ? this.$t('sw-import-export.activity.contextMenu.downloadExportFile')
                : this.$t('sw-import-export.activity.contextMenu.downloadImportFile');
        },

        // show when not loading and logs are there
        showGrid() {
            return !this.isLoading && !!this.logs.length > 0;
        },

        // show when not loading and logs aren't there
        showEmptyState() {
            return !this.isLoading && !!this.logs.length <= 0;
        },

        // show when loading
        showSpinner() {
            return this.isLoading;
        },

        emptyStateSubLine() {
            return this.type === 'export'
                ? this.$t('sw-import-export.activity.emptyState.subLineExport')
                : this.$t('sw-import-export.activity.emptyState.subLineImport');
        },

        emptyStateTitle() {
            return this.type === 'export'
                ? this.$t('sw-import-export.activity.emptyState.titleExport')
                : this.$t('sw-import-export.activity.emptyState.titleImport');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    watch: {
        hasActivitiesInProgress(hasActivitiesInProgress) {
            if (hasActivitiesInProgress && !this.activitiesReloadTimer) {
                this.activitiesReloadTimer = window.setInterval(
                    this.updateActivitiesInProgress.bind(this),
                    this.activitiesReloadIntervall,
                );
            } else if (this.activitiesReloadTimer) {
                window.clearInterval(this.activitiesReloadTimer);
                this.activitiesReloadTimer = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        if (this.activitiesReloadTimer) {
            window.clearInterval(this.activitiesReloadTimer);
        }
    },

    methods: {
        createdComponent() {
            return this.fetchActivities();
        },

        addActivity(log) {
            this.logs.addAt(log, 0);
        },

        async fetchActivities() {
            this.isLoading = true;

            this.logRepository
                .search(this.activityCriteria)
                .then((result) => {
                    if (!(result instanceof EntityCollection)) {
                        return Promise.reject(new Error(this.$t('global.notification.notificationLoadingDataErrorMessage')));
                    }

                    this.updateActivitiesFromLogs(result);

                    this.logs = result;

                    return Promise.resolve();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error?.message ?? this.$t('global.notification.notificationLoadingDataErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        async updateActivitiesInProgress() {
            const criteria = Criteria.fromCriteria(this.activityCriteria);
            criteria.setIds(this.logs.filter((log) => log.state === 'progress').getIds());
            criteria.addAssociation('file');

            this.logRepository
                .search(criteria)
                .then((result) => {
                    if (!(result instanceof EntityCollection)) {
                        return Promise.reject(new Error(this.$t('global.notification.notificationLoadingDataErrorMessage')));
                    }

                    this.updateActivitiesFromLogs(result);

                    return Promise.resolve();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error?.message ?? this.$t('global.notification.notificationLoadingDataErrorMessage'),
                    });
                });
        },

        updateActivitiesFromLogs(logs) {
            logs.forEach((log) => {
                const activity = this.logs.get(log.id);

                if (!activity) {
                    return;
                }

                const originalState = activity.state;
                Object.keys(log).forEach((key) => {
                    activity[key] = log[key];
                });

                if (originalState === log.state) {
                    return;
                }

                const config = {
                    message: this.$tc(
                        this.stateText?.[log.activity]?.[log.state] ?? '',
                        log.state === 'failed' && log.invalidRecordsLog ? 2 : 1,
                        {
                            profile: log.profileName,
                        },
                    ),
                };

                if (log.state === 'succeeded') {
                    this.createNotificationSuccess(config);

                    if (log.activity === 'import' && log.records === 0) {
                        this.createNotificationWarning({
                            message: this.$t('sw-import-export.importer.messageImportWarning'),
                        });
                    }

                    return;
                }

                this.createNotificationError(config);
            });
        },

        async onOpenProfile(id) {
            this.profileRepository
                .get(id)
                .then((result) => {
                    this.selectedProfile = result;
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error?.message ?? this.$t('global.notification.notificationLoadingDataErrorMessage'),
                    });
                });
        },

        onAbortProcess(item) {
            this.importExport.cancel(item.id).then(() => {
                this.fetchActivities();
            });
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        onShowLog(item) {
            this.selectedLog = item;
            this.showDetailModal = true;
        },

        onShowResult(item) {
            this.selectedLog = item;
            this.showResultModal = true;
        },

        closeSelectedLog() {
            this.selectedLog = null;
            this.showDetailModal = false;
        },

        closeSelectedResult() {
            this.selectedResult = null;
            this.showResultModal = false;
        },

        async openProcessFileDownload(item) {
            if (this.type === 'export' && item.state !== 'succeeded') {
                return null;
            }

            return window.open(await this.importExport.getDownloadUrl(item.fileId), '_blank');
        },

        saveSelectedProfile() {
            this.isLoading = true;
            this.profileRepository
                .save(this.selectedProfile)
                .then(() => {
                    this.selectedProfile = null;
                    this.createNotificationSuccess({
                        message: this.$t('sw-import-export.profile.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-import-export.profile.messageSaveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        calculateFileSize(size) {
            return format.fileSize(size);
        },

        getStateLabel(state) {
            const translationKey = `sw-import-export.activity.status.${state}`;

            return this.$te(translationKey) ? this.$t(translationKey) : state;
        },

        getStateClass(state) {
            return {
                'sw-import-export-activity__progress-indicator': state === 'progress',
            };
        },
    },
};
