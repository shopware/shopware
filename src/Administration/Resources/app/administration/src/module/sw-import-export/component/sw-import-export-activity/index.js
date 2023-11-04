/**
 * @package system-settings
 */
import template from './sw-import-export-activity.html.twig';
import './sw-import-export-activity.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { format } = Shopware.Utils;

/**
 * @private
 */
export default {
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
                criteria.addFilter(Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('activity', 'import'),
                        Criteria.equals('activity', 'dryrun'),
                    ],
                ));
                criteria.getAssociation('invalidRecordsLog')
                    .addAssociation('file');
            } else if (this.type === 'export') {
                criteria.addFilter(Criteria.equals('activity', 'export'));
            }

            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            criteria.setPage(1);
            criteria.addAssociation('user');
            criteria.addAssociation('file');
            criteria.addAssociation('profile');

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
                }];
        },

        hasActivitiesInProgress() {
            if (!this.logs) {
                return false;
            }

            return this.logs.filter(log => log.state === 'progress').length > 0;
        },

        downloadFileText() {
            return this.type === 'export' ?
                this.$tc('sw-import-export.activity.contextMenu.downloadExportFile') :
                this.$tc('sw-import-export.activity.contextMenu.downloadImportFile');
        },

        // show when not loading and logs are there
        showGrid() {
            return !this.isLoading && !!this.logs?.length > 0;
        },

        // show when not loading and logs aren't there
        showEmptyState() {
            return !this.isLoading && !!this.logs?.length <= 0;
        },

        // show when loading
        showSpinner() {
            return this.isLoading;
        },

        emptyStateSubLine() {
            return this.type === 'export' ?
                this.$tc('sw-import-export.activity.emptyState.subLineExport') :
                this.$tc('sw-import-export.activity.emptyState.subLineImport');
        },

        emptyStateTitle() {
            return this.type === 'export' ?
                this.$tc('sw-import-export.activity.emptyState.titleExport') :
                this.$tc('sw-import-export.activity.emptyState.titleImport');
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

    destroyed() {
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

            const logs = await this.logRepository.search(this.activityCriteria);

            if (this.logs) {
                this.updateActivitiesFromLogs(logs);
            }

            this.logs = logs;

            this.isLoading = false;
        },

        async updateActivitiesInProgress() {
            const criteria = Criteria.fromCriteria(this.activityCriteria);
            criteria.setIds(this.logs.filter(log => log.state === 'progress').getIds());
            criteria.addAssociation('file');
            const currentInProgress = await this.logRepository.search(criteria);

            this.updateActivitiesFromLogs(currentInProgress);
        },

        updateActivitiesFromLogs(logs) {
            Object.values(logs).forEach((log) => {
                const activity = this.logs.get(log.id);

                if (!activity) {
                    return;
                }

                const originalState = activity.state;
                Object.keys(log).forEach(key => {
                    activity[key] = log[key];
                });

                if (originalState === log.state) {
                    return;
                }

                const config = {
                    message: this.$t(this.stateText[log.activity][log.state], {
                        profile: log.profileName,
                    }),
                    autoClose: false,
                };

                if (log.state === 'succeeded') {
                    this.createNotificationSuccess(config);

                    if (log.activity === 'import' && log.records === 0) {
                        this.createNotificationWarning({
                            message: this.$tc('sw-import-export.importer.messageImportWarning', 0),
                            autoClose: false,
                        });
                    }

                    return;
                }

                this.createNotificationError(config);
            });
        },

        async onOpenProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id);
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

        getStateClass(state) {
            return {
                'sw-import-export-activity__progress-indicator': state === 'progress',
            };
        },
    },
};
