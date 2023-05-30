import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/base/sw-modal';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/grid/sw-grid-column';
import 'src/app/component/base/sw-card';
import swImportExportActivity from 'src/module/sw-import-export/component/sw-import-export-activity';
import swImportExportActivityResultModal from 'src/module/sw-import-export/component/sw-import-export-activity-result-modal';
import swImportExportActivityLogInfoModal from 'src/module/sw-import-export/component/sw-import-export-activity-log-info-modal';

Shopware.Component.register('sw-import-export-activity', swImportExportActivity);
Shopware.Component.register('sw-import-export-activity-result-modal', swImportExportActivityResultModal);
Shopware.Component.register('sw-import-export-activity-log-info-modal', swImportExportActivityLogInfoModal);

describe('module/sw-import-export/components/sw-import-export-activity', () => {
    function getLogData() {
        return [
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
        ];
    }

    const createWrapper = async (options = {}) => {
        const defaultOptions = {
            stubs: {
                'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
                'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
                'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
                'sw-context-button': await Shopware.Component.build('sw-context-button'),
                'sw-import-export-activity-result-modal': await Shopware.Component.build('sw-import-export-activity-result-modal'),
                'sw-import-export-edit-profile-modal': {
                    template: '<div></div>',
                },
                'sw-import-export-activity-log-info-modal': await Shopware.Component.build('sw-import-export-activity-log-info-modal'),
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-grid': await Shopware.Component.build('sw-grid'),
                'sw-grid-row': await Shopware.Component.build('sw-grid-row'),
                'sw-grid-column': await Shopware.Component.build('sw-grid-column'),
                'sw-card': await Shopware.Component.build('sw-card'),
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
                $tc: (key) => {
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
                importExport: new ImportExportService(),
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => {
                                return new EntityCollection(null, null, null, new Criteria(1, 25), options.logData);
                            },
                        };
                    },
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
        };

        const wrapper = shallowMount(
            await Shopware.Component.build('sw-import-export-activity'),
            Object.assign(defaultOptions, options),
        );

        return { wrapper };
    };

    it('should be a Vue.js component', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the activity detail modal', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });
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

        const detailModal = wrapper.find('.sw-import-export-activity-log-info-modal');

        expect(wrapper.vm).toBeTruthy();
        expect(detailModal.vm).toBeTruthy();
        expect(detailModal.vm.logEntity).toEqual(logEntity);
    });

    it('should not download export file in progress state', async () => {
        const logData = getLogData();
        logData[0].state = 'pending';

        const { wrapper } = await createWrapper({ logData });
        await wrapper.setProps({ type: 'export' });
        await wrapper.vm.$nextTick();

        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');

        expect(contextButton.find('.sw-context-menu-item__text.is--disabled').exists()).toBeTruthy();
    });

    it('should download export file in succeed state', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });
        await wrapper.setProps({ type: 'export' });
        await wrapper.vm.$nextTick();

        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');

        expect(contextButton.find('.sw-context-menu-item__text.is--disabled').exists()).toBeFalsy();
    });

    it('should open the activity result modal', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });

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
        const resultModal = wrapper.find('.sw-import-export-activity-result-modal');

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
        const { wrapper } = await createWrapper({ logData: getLogData() });

        expect(wrapper.vm.getStateLabel('progress')).toBe('Progress');
        expect(wrapper.vm.getStateLabel('merging_files')).toBe('Merging files');
        expect(wrapper.vm.getStateLabel('succeeded')).toBe('Succeeded');
        expect(wrapper.vm.getStateLabel('failed')).toBe('Failed');
        expect(wrapper.vm.getStateLabel('aborted')).toBe('Aborted');
    });

    it('should show the technical name when no translation exists', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });

        expect(wrapper.vm.getStateLabel('waiting')).toBe('waiting');
    });

    it('should have the status field as the third position in grid', async () => {
        const { wrapper } = await createWrapper({ logData: getLogData() });
        await flushPromises();

        const gridHeaders = wrapper.findAll('.sw-data-grid__cell--header');
        const stateHeader = gridHeaders.at(2);

        expect(stateHeader.text()).toBe('sw-import-export.activity.columns.state');
    });
});
