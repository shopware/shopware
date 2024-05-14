import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const logDataExport = {
    aborted: [
        {
            activity: 'export',
            state: 'aborted',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    empty: [],
    failed: [
        {
            activity: 'export',
            state: 'failed',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    failedWithLog: [
        {
            activity: 'export',
            state: 'failed',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
            invalidRecordsLog: {
                activity: 'invalid_records_export',
                state: 'succeeded',
            },
        },
    ],
    pending: [
        {
            activity: 'export',
            state: 'pending',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    progress: [
        {
            activity: 'export',
            state: 'progress',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    succeeded: [
        {
            activity: 'export',
            state: 'succeeded',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
};

const logDataImport = {
    aborted: [
        {
            activity: 'import',
            state: 'aborted',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    empty: [],
    failed: [
        {
            activity: 'import',
            state: 'failed',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    failedWithLog: [
        {
            activity: 'import',
            state: 'failed',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
            invalidRecordsLog: {
                activity: 'invalid_records_export',
                state: 'succeeded',
            },
        },
    ],
    pending: [
        {
            activity: 'import',
            state: 'pending',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    progress: [
        {
            activity: 'import',
            state: 'progress',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    succeeded: [
        {
            activity: 'import',
            state: 'succeeded',
            records: 1,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
    succeededWithoutRecords: [
        {
            activity: 'import',
            state: 'succeeded',
            records: 0,
            userId: '004ade6297c54bcbaf94e7bf09aa0bac',
            profileId: '6c7255662b63413f97d25a6e9a16fa6f',
            fileId: '645cfb7d036142c7b817ffefb89ac097',
            invalidRecordsLogId: null,
            username: 'admin',
            profileName: 'Default category',
            result: {},
            apiAlias: null,
            id: 'e9944f64935d4fe3a7b53207f6cfa137',
            user: {
                localeId: '69b9ec7987a043dfa15c2feaaa219bae',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
                active: true,
                admin: true,
                lastUpdatedPasswordAt: null,
                timeZone: 'UTC',
            },
            profile: {
                name: 'Default category',
                label: 'Default category',
                systemDefault: true,
                sourceEntity: 'category',
                fileType: 'text/csv',
                delimiter: ';',
                enclosure: '"',
                config: {},
                type: 'import-export',
                updatedAt: null,
                translated: {
                    label: 'Default category',
                },
                apiAlias: null,
                id: '6c7255662b63413f97d25a6e9a16fa6f',
                importExportLogs: [],
                translations: [],
            },
            file: {
                originalName: 'Default category_20210920-112247.csv',
                path: 'export/645cfb7d/036142c7/b817ffef/b89ac097',
                size: 263,
                accessToken: null,
                apiAlias: null,
                id: '645cfb7d036142c7b817ffefb89ac097',
            },
        },
    ],
};

function getImportExportServiceMock() {
    const importExportService = new ImportExportService();

    importExportService.cancel = jest.fn(() => Promise.resolve());

    return importExportService;
}

function getEntityCollection(entities = []) {
    return new EntityCollection(null, null, null, new Criteria(1, 25), JSON.parse(JSON.stringify(entities)));
}

const importExportLogRepositoryMock = {
    search: jest.fn(() => {
        return Promise.resolve(getEntityCollection(logDataExport.progress));
    }),
};

const importExportProfileRepositoryMock = {
    get: jest.fn(() => {
        return Promise.resolve({
            name: 'foo profile',
            id: '018ea87897aa7229a3089e80bb364a0e',
        });
    }),
    save: jest.fn(() => {
        return Promise.resolve();
    }),
};

const createWrapper = async (options = {}) => {
    const defaultOptions = {
        global: {
            stubs: {
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-import-export-activity-result-modal': await wrapTestComponent('sw-import-export-activity-result-modal', { sync: true }),
                'sw-import-export-edit-profile-modal': {
                    template: `
                        <div
                            v-if="profile"
                            class="sw-import-export-edit-profile-modal"
                        >
                            <button class="modal-action__save" @click="$emit('profile-save')">Save</button>
                            <button class="modal-action__close" @click="$emit('profile-close')">Close</button>
                        </div>
                    `,
                    props: {
                        profile: {
                            required: true,
                            type: Object,
                        },
                    },
                },
                'sw-import-export-activity-log-info-modal': await wrapTestComponent('sw-import-export-activity-log-info-modal', { sync: true }),
                'sw-grid': await wrapTestComponent('sw-grid'),
                'sw-grid-row': await wrapTestComponent('sw-grid-row'),
                'sw-grid-column': await wrapTestComponent('sw-grid-column'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
                'sw-icon': true,
                'sw-description-list': true,
                'sw-color-badge': true,
                'sw-button': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-pagination': true,
                'sw-help-text': true,
                'sw-loader': true,
                'sw-extension-component-section': true,
            },
            mocks: {
                $tc: (key, pluralization) => {
                    switch (key) {
                        default: {
                            return { key, pluralization };
                        }
                    }
                },
                $t: (key) => {
                    switch (key) {
                        case 'sw-import-export.activity.status.progress': {
                            return 'Progress';
                        }

                        case 'sw-import-export.activity.status.merging_files': {
                            return 'Merging files';
                        }

                        case 'sw-import-export.activity.status.succeeded': {
                            return 'Succeeded';
                        }

                        case 'sw-import-export.activity.status.failed': {
                            return 'Failed';
                        }

                        case 'sw-import-export.activity.status.aborted': {
                            return 'Aborted';
                        }

                        default: {
                            return key;
                        }
                    }
                },
                $te: (key) => {
                    return [
                        'sw-import-export.activity.status.progress',
                        'sw-import-export.activity.status.merging_files',
                        'sw-import-export.activity.status.succeeded',
                        'sw-import-export.activity.status.failed',
                        'sw-import-export.activity.status.aborted',
                    ].includes(key);
                },
                date: (date) => date,
            },
            provide: {
                importExport: getImportExportServiceMock(),
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'import_export_log') {
                            return importExportLogRepositoryMock;
                        }
                        if (entity === 'import_export_profile') {
                            return importExportProfileRepositoryMock;
                        }
                        return {};
                    },
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
        },
    };

    const wrapper = mount(
        await wrapTestComponent('sw-import-export-activity', { sync: true }),
        Object.assign(defaultOptions, options),
    );

    return { wrapper };
};

describe('module/sw-import-export/components/sw-import-export-activity', () => {
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should open the activity detail modal', async () => {
        const { wrapper } = await createWrapper();
        const logEntity = {
            id: 'id',
            file: {
                originalName: 'originalName',
                id: 'fileId',
                accessToken: 'accessToken',
                size: 100,
            },
            profile: {
                label: 'My profile',
            },
            type: 'import',
            username: 'username',
            records: 1,
            createdAt: '2020-04-03T12:23:02+00:00',
            state: 'succeeded',
        };

        await wrapper.setData({ selectedLog: logEntity, showDetailModal: true });
        await flushPromises();

        const detailModal = wrapper.getComponent('.sw-import-export-activity-log-info-modal');

        expect(wrapper.vm).toBeTruthy();
        expect(detailModal.vm).toBeTruthy();
        expect(detailModal.vm.logEntity).toEqual(logEntity);
    });

    it('should not download export file in progress state', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        const { wrapper } = await createWrapper();
        await wrapper.setProps({ type: 'export' });
        await flushPromises();

        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');
        await flushPromises();

        expect(contextButton.find('.sw-context-menu-item__text.is--disabled').exists()).toBeTruthy();
    });

    it('should download export file in succeed state', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.setProps({ type: 'export' });
        await flushPromises();

        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');

        expect(contextButton.find('.sw-context-menu-item__text.is--disabled').exists()).toBeFalsy();
    });

    it('should open the activity result modal', async () => {
        const { wrapper } = await createWrapper();

        const logEntity = {
            id: 'id',
            file: {
                originalName: 'originalName',
                id: 'fileId',
                accessToken: 'accessToken',
                size: 100,
            },
            type: 'import',
            username: 'username',
            records: 1,
            createdAt: '2020-04-03T12:23:02+00:00',
            state: 'succeeded',
            profile: {
                sourceEntity: 'product',
            },
            result: {
                product: {
                    insert: 1,
                    update: 2,
                    insertError: 3,
                    updateError: 4,
                    insertSkip: 5,
                    updateSkip: 6,
                    otherError: 1,
                },
                tax: {
                    insert: 7,
                    update: 8,
                    insertError: 9,
                    updateError: 10,
                    insertSkip: 11,
                    updateSkip: 12,
                    otherError: 0,
                },
            },
        };
        await wrapper.vm.onShowResult(logEntity);
        await flushPromises();

        const resultModal = wrapper.getComponent('.sw-import-export-activity-result-modal');

        expect(wrapper.vm).toBeTruthy();
        expect(resultModal.vm).toBeTruthy();
        expect(resultModal.vm.mainEntityResult).toEqual({
            insert: 1,
            update: 2,
            insertError: 3,
            updateError: 4,
            insertSkip: 5,
            updateSkip: 6,
            otherError: 1,
        });
        expect(resultModal.vm.result).toEqual([{
            entityName: 'tax',
            insert: 7,
            update: 8,
            insertError: 9,
            updateError: 10,
            insertSkip: 11,
            updateSkip: 12,
            otherError: 0,
        }]);

        const mainActivityPrefix = '.sw-import-export-activity-result-modal__main-activity';
        expect(resultModal.find(`${mainActivityPrefix}-insert dd`).text()).toBe('1');
        expect(resultModal.find(`${mainActivityPrefix}-update dd`).text()).toBe('2');
        expect(resultModal.find(`${mainActivityPrefix}-insert-error dd`).text()).toBe('3');
        expect(resultModal.find(`${mainActivityPrefix}-update-error dd`).text()).toBe('4');
        expect(resultModal.find(`${mainActivityPrefix}-insert-skip dd`).text()).toBe('5');
        expect(resultModal.find(`${mainActivityPrefix}-update-skip dd`).text()).toBe('6');
        expect(resultModal.find(`${mainActivityPrefix}-other-error dd`).text()).toBe('1');

        const columnClassPrefix = '.sw-import-export-activity-result-modal__column-tax';
        expect(resultModal.find(`${columnClassPrefix}-label`).text()).toBe('tax');
        expect(resultModal.find(`${columnClassPrefix}-changes`).text()).toBe('15');
        expect(resultModal.find(`${columnClassPrefix}-errors`).text()).toBe('19');
        expect(resultModal.find(`${columnClassPrefix}-skipped`).text()).toBe('23');
    });

    it('should show the correct label', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.getStateLabel('progress')).toBe('Progress');
        expect(wrapper.vm.getStateLabel('merging_files')).toBe('Merging files');
        expect(wrapper.vm.getStateLabel('succeeded')).toBe('Succeeded');
        expect(wrapper.vm.getStateLabel('failed')).toBe('Failed');
        expect(wrapper.vm.getStateLabel('aborted')).toBe('Aborted');
    });

    it('should show the technical name when no translation exists', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.getStateLabel('waiting')).toBe('waiting');
    });

    it('should have the status field as the third position in grid', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        const gridHeaders = wrapper.findAll('.sw-data-grid__cell--header');
        const stateHeader = gridHeaders.at(2);

        expect(stateHeader.text()).toBe('sw-import-export.activity.columns.state');
    });

    it('should add associations no longer autoload in the activityCriteria', async () => {
        const { wrapper } = await createWrapper();
        const criteria = wrapper.vm.activityCriteria;

        expect(criteria.hasAssociation('file')).toBe(true);
        expect(criteria.getAssociation('invalidRecordsLog').hasAssociation('file')).toBe(true);
    });

    it('should show empty state for the export', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection([]));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-import-export-activity > sw-empty-state')).toBeDefined();
        expect(wrapper.find('sw-empty-state').attributes('title')).toBe('sw-import-export.activity.emptyState.titleExport');
        expect(wrapper.find('sw-empty-state').attributes('subline')).toBe('sw-import-export.activity.emptyState.subLineExport');
    });

    it('should show an export in progress', async () => {
        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');
    });

    it('should not change an export in progress when the status did not change', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        jest.clearAllTimers();
    });

    it('should change an export status from progress to succeeded', async () => {
        jest.useFakeTimers();

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationSuccess = jest.spyOn(wrapper.vm, 'createNotificationSuccess');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.succeeded));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Succeeded');
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.exporter.messageExportSuccess',
                pluralization: 1,
            },
        });

        jest.clearAllTimers();
    });

    it('should change an export status from progress to failed', async () => {
        jest.useFakeTimers();

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.failed));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Failed');
        expect(createNotificationError).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.exporter.messageExportError',
                pluralization: 1,
            },
        });

        await wrapper.find('.sw-data-grid__body > .sw-data-grid__row--0 > .sw-data-grid__cell--actions .sw-context-button__button').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-data-grid__body .sw-import-export-activity__download-action:nth-of-type(2)')).toHaveLength(0);

        jest.clearAllTimers();
    });

    it('should change an export status from progress to failed with log', async () => {
        jest.useFakeTimers();

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.failedWithLog));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Failed');
        expect(createNotificationError).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.exporter.messageExportError',
                pluralization: 2,
            },
        });

        await wrapper.find('.sw-data-grid__body > .sw-data-grid__row--0 > .sw-data-grid__cell--actions .sw-context-button__button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__body .sw-import-export-activity__download-action:nth-of-type(2)')).toBeDefined();

        jest.clearAllTimers();
    });

    it('should handle errors when initially loading export activities and the result is not an entity collection', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('sw-empty-state')).toBeTruthy();
        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });
    });

    it('should handle errors when initially loading export activities', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('sw-empty-state')).toBeTruthy();
        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });
    });

    it('should handle errors when loading export activities and the result is not an entity collection', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(null);
        });
        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });

        jest.clearAllTimers();
    });

    it('should handle errors when loading export activities', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });
        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });

        jest.clearAllTimers();
    });

    it('should show empty state for the import', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection([]));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-import-export-activity > sw-empty-state')).toBeDefined();
        expect(wrapper.find('sw-empty-state').attributes('title')).toBe('sw-import-export.activity.emptyState.titleImport');
        expect(wrapper.find('sw-empty-state').attributes('subline')).toBe('sw-import-export.activity.emptyState.subLineImport');
    });

    it('should show an import in progress', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');
    });

    it('should not change an import in progress when the status did not change', async () => {
        jest.useFakeTimers();

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        jest.clearAllTimers();
    });

    it('should change an import status from progress to succeeded', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationSuccess = jest.spyOn(wrapper.vm, 'createNotificationSuccess');
        const createNotificationWarning = jest.spyOn(wrapper.vm, 'createNotificationWarning');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.succeeded));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Succeeded');
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.importer.messageImportSuccess',
                pluralization: 1,
            },
        });
        expect(createNotificationWarning).toHaveBeenCalledTimes(0);

        jest.clearAllTimers();
    });

    it('should display a warning when an import succeeds without importing any entities', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationSuccess = jest.spyOn(wrapper.vm, 'createNotificationSuccess');
        const createNotificationWarning = jest.spyOn(wrapper.vm, 'createNotificationWarning');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.succeededWithoutRecords));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Succeeded');
        expect(createNotificationSuccess).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.importer.messageImportSuccess',
                pluralization: 1,
            },
        });
        expect(createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-import-export.importer.messageImportWarning',
        });

        jest.clearAllTimers();
    });

    it('should change an import status from progress to failed', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.failed));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Failed');
        expect(createNotificationError).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.importer.messageImportError',
                pluralization: 1,
            },
        });

        await wrapper.find('.sw-data-grid__body > .sw-data-grid__row--0 > .sw-data-grid__cell--actions .sw-context-button__button').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-data-grid__body .sw-import-export-activity__download-action:nth-of-type(2)')).toHaveLength(0);

        jest.clearAllTimers();
    });

    it('should change an import status from progress to failed with log', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Progress');

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.failedWithLog));
        });

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Failed');
        expect(createNotificationError).toHaveBeenCalledWith({
            message: {
                key: 'sw-import-export.importer.messageImportError',
                pluralization: 2,
            },
        });

        await wrapper.find('.sw-data-grid__body > .sw-data-grid__row--0 > .sw-data-grid__cell--actions .sw-context-button__button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__body .sw-import-export-activity__download-action:nth-of-type(2)')).toBeDefined();

        jest.clearAllTimers();
    });

    it('should handle errors when initially loading import activities and the result is not an entity collection', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('sw-empty-state')).toBeTruthy();
        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });
    });

    it('should handle errors when initially loading import activities', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        expect(wrapper.find('sw-empty-state')).toBeTruthy();
        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });
    });

    it('should handle errors when loading import activities and the result is not an entity collection', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(null);
        });
        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });

        jest.clearAllTimers();
    });

    it('should handle errors when loading import activities', async () => {
        jest.useFakeTimers();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataImport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'import',
            },
        });
        const createNotificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.reject();
        });
        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(createNotificationError).toHaveBeenCalledWith({ message: 'global.notification.notificationLoadingDataErrorMessage' });

        jest.clearAllTimers();
    });

    it('should abort running export', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.progress));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.aborted));
        });

        await wrapper.find('.sw-data-grid__row--0 > .sw-data-grid__cell--actions button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-import-export-activity__abort-process-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--state').text()).toBe('Aborted');
    });

    it('should add logs', async () => {
        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        expect(wrapper.vm.logs).toHaveLength(1);
        expect(wrapper.vm.logs.getAt(0)).toStrictEqual(logDataExport.progress[0]);

        wrapper.vm.addActivity(logDataImport.succeeded[0]);

        expect(wrapper.vm.logs).toHaveLength(2);
        expect(wrapper.vm.logs.getAt(0)).toStrictEqual(logDataImport.succeeded[0]);
        expect(wrapper.vm.logs.getAt(1)).toStrictEqual(logDataExport.progress[0]);
    });

    it('should should open and close the edit profile modal', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.succeeded));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 > .sw-data-grid__cell--actions button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-context-menu-item:nth-of-type(2)').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(1);
        expect(wrapper.findComponent('.sw-import-export-edit-profile-modal').vm.profile).toStrictEqual({
            name: 'foo profile',
            id: '018ea87897aa7229a3089e80bb364a0e',
        });

        await wrapper.find('.sw-import-export-edit-profile-modal > .modal-action__close').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(0);
    });

    it('should close the edit profile modal after an successful save', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.succeeded));
        });

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const notificationSuccess = jest.spyOn(wrapper.vm, 'createNotificationSuccess');
        const notificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 > .sw-data-grid__cell--actions button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-context-menu-item:nth-of-type(2)').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(1);
        expect(wrapper.findComponent('.sw-import-export-edit-profile-modal').vm.profile).toStrictEqual({
            name: 'foo profile',
            id: '018ea87897aa7229a3089e80bb364a0e',
        });

        await wrapper.find('.sw-import-export-edit-profile-modal > .modal-action__save').trigger('click');
        await flushPromises();

        expect(notificationError).toHaveBeenCalledTimes(0);
        expect(notificationSuccess).toHaveBeenCalledTimes(1);
        expect(notificationSuccess).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSaveSuccess',
        });
        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(0);
    });

    it('should not close the edit profile after an unsuccessful save', async () => {
        importExportLogRepositoryMock.search.mockImplementationOnce(() => {
            return Promise.resolve(getEntityCollection(logDataExport.succeeded));
        });

        importExportProfileRepositoryMock.save.mockImplementationOnce(() => Promise.reject());

        const { wrapper } = await createWrapper({
            props: {
                type: 'export',
            },
        });
        const notificationSuccess = jest.spyOn(wrapper.vm, 'createNotificationSuccess');
        const notificationError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 > .sw-data-grid__cell--actions button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-context-menu-item:nth-of-type(2)').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(1);
        expect(wrapper.findComponent('.sw-import-export-edit-profile-modal').vm.profile).toStrictEqual({
            name: 'foo profile',
            id: '018ea87897aa7229a3089e80bb364a0e',
        });

        await wrapper.find('.sw-import-export-edit-profile-modal > .modal-action__save').trigger('click');
        await flushPromises();

        expect(notificationSuccess).toHaveBeenCalledTimes(0);
        expect(notificationError).toHaveBeenCalledTimes(1);
        expect(notificationError).toHaveBeenCalledWith({
            message: 'sw-import-export.profile.messageSaveError',
        });
        expect(wrapper.findAll('.sw-import-export-edit-profile-modal')).toHaveLength(1);
    });
});
