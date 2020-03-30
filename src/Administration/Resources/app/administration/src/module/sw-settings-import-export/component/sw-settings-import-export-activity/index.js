import template from './sw-settings-import-export-activity.html.twig';
import './sw-settings-import-export-activity.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { format } = Shopware.Utils;

Shopware.Component.register('sw-settings-import-export-activity', {
    template,

    inject: ['repositoryFactory', 'importExport'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        type: {
            type: String,
            required: false,
            default: 'import',
            validValues: [
                'import',
                'export'
            ],
            validator(value) {
                return [
                    'import',
                    'export'
                ].includes(value);
            }
        }
    },

    data() {
        return {
            logs: null,
            isLoading: false,
            selectedProfile: null
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
                criteria.addFilter(Criteria.equals('activity', 'import'));
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
            const columns = [
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-settings-import-export.activity.columns.date',
                    // routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true
                }, {
                    property: 'profileName',
                    dataIndex: 'profileName',
                    label: 'sw-settings-import-export.activity.columns.profile',
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'records',
                    dataIndex: 'records',
                    label: 'sw-settings-import-export.activity.columns.records',
                    allowResize: true,
                    primary: false
                }];
            if (this.type === 'import') {
                columns.push({
                    property: 'invalidRecords',
                    dataIndex: 'records',
                    label: 'sw-settings-import-export.activity.columns.invalidRecords',
                    allowResize: true,
                    primary: false
                });
            }
            columns.push(...[
                {
                    property: 'file.size',
                    dataIndex: 'file.size',
                    label: 'sw-settings-import-export.activity.columns.size',
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'user.lastName',
                    dataIndex: 'user.lastName',
                    label: 'sw-settings-import-export.activity.columns.user',
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'state',
                    dataIndex: 'state',
                    label: 'sw-settings-import-export.activity.columns.state',
                    allowResize: true,
                    primary: false
                }]);

            return columns;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchActivities();
        },

        async fetchActivities() {
            this.isLoading = true;

            this.logs = await this.logRepository.search(this.activityCriteria, Shopware.Context.api);

            this.isLoading = false;
        },

        async onOpenProfile(id) {
            this.selectedProfile = await this.profileRepository.get(id, Shopware.Context.api);
        },

        closeSelectedProfile() {
            this.selectedProfile = null;
        },

        getDownloadUrl(id, accessToken) {
            return this.importExport.getDownloadUrl(id, accessToken);
        },

        saveSelectedProfile() {
            this.isLoading = true;
            this.profileRepository.save(this.selectedProfile, Shopware.Context.api).then(() => {
                this.selectedProfile = null;
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveSuccess'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveSuccess', 0)
                });
                return this.loadProfiles();
            }).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-import-export.profile.titleSaveError'),
                    message: this.$tc('sw-settings-import-export.profile.messageSaveError', 0)
                });
            });
        },

        calculateFileSize(size) {
            return format.fileSize(size);
        }
    }
});
