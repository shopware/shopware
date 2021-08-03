import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-modal';
import 'src/app/component/grid/sw-grid';
import 'src/app/component/grid/sw-grid-row';
import 'src/app/component/grid/sw-grid-column';
import 'src/module/sw-import-export/component/sw-import-export-activity';
import 'src/module/sw-import-export/component/sw-import-export-activity-detail-modal';
import 'src/module/sw-import-export/component/sw-import-export-activity-result-modal';

describe('module/sw-import-export/components/sw-import-export-activity', () => {
    const createWrapper = (options = {}) => {
        const defaultOptions = {
            stubs: {
                'sw-entity-listing': {
                    template: '<div></div>'
                },
                'sw-import-export-activity-detail-modal': Shopware.Component.build('sw-import-export-activity-detail-modal'),
                'sw-import-export-activity-result-modal': Shopware.Component.build('sw-import-export-activity-result-modal'),
                'sw-import-export-edit-profile-modal': {
                    template: '<div></div>'
                },
                'sw-modal': Shopware.Component.build('sw-modal'),
                'sw-grid': Shopware.Component.build('sw-grid'),
                'sw-grid-row': Shopware.Component.build('sw-grid-row'),
                'sw-grid-column': Shopware.Component.build('sw-grid-column'),
                'sw-icon': true,
                'sw-description-list': true,
                'sw-color-badge': true,
                'sw-button': true
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
                        'sw-import-export.activity.status.aborted'
                    ].includes(key);
                },
                date: (date) => date
            },
            provide: {
                importExport: new ImportExportService(),
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => {
                                return [];
                            }
                        };
                    }
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {}
                }
            }
        };

        const wrapper = shallowMount(
            Shopware.Component.build('sw-import-export-activity'),
            Object.assign(defaultOptions, options)
        );

        return { wrapper };
    };

    it('should be a Vue.js component', async () => {
        const { wrapper } = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the activity detail modal', async () => {
        const { wrapper } = createWrapper();
        const logEntity = {
            id: 'id',
            file: {
                originalName: 'originalName',
                id: 'fileId',
                accessToken: 'accessToken',
                size: 100
            },
            type: 'import',
            username: 'username',
            records: 1,
            createdAt: '2020-04-03T12:23:02+00:00',
            state: 'succeeded'
        };

        await wrapper.setData({ selectedLog: logEntity });

        const detailModal = wrapper.find('.sw-import-export-activity-detail-modal');

        expect(wrapper.vm).toBeTruthy();
        expect(detailModal.vm).toBeTruthy();
        expect(detailModal.vm.logEntity).toEqual(logEntity);
    });

    it('should open the activity result modal', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_8097'];

        const { wrapper } = createWrapper();
        const logResult = {
            product: {
                insert: 1,
                update: 2,
                insertError: 3,
                updateError: 4,
                insertSkip: 5,
                updateSkip: 6
            }
        };

        await wrapper.vm.onShowResult(logResult);

        const resultModal = wrapper.find('.sw-import-export-activity-result-modal');

        expect(wrapper.vm).toBeTruthy();
        expect(resultModal.vm).toBeTruthy();
        expect(resultModal.vm.result).toEqual([{
            entityName: 'product',
            insert: 1,
            update: 2,
            insertError: 3,
            updateError: 4,
            insertSkip: 5,
            updateSkip: 6
        }]);

        const columnClassPrefix = '.sw-import-export-activity-result-modal__column-product';
        expect(resultModal.find(`${columnClassPrefix}-label`).text()).toBe('product');
        expect(resultModal.find(`${columnClassPrefix}-insert`).text()).toBe('1');
        expect(resultModal.find(`${columnClassPrefix}-update`).text()).toBe('2');
        expect(resultModal.find(`${columnClassPrefix}-insert-error`).text()).toBe('3');
        expect(resultModal.find(`${columnClassPrefix}-update-error`).text()).toBe('4');
        expect(resultModal.find(`${columnClassPrefix}-insert-skip`).text()).toBe('5');
        expect(resultModal.find(`${columnClassPrefix}-update-skip`).text()).toBe('6');
    });

    it('should show the correct label', async () => {
        const { wrapper } = createWrapper();

        expect(wrapper.vm.getStateLabel('progress')).toEqual('Progress');
        expect(wrapper.vm.getStateLabel('merging_files')).toEqual('Merging files');
        expect(wrapper.vm.getStateLabel('succeeded')).toEqual('Succeeded');
        expect(wrapper.vm.getStateLabel('failed')).toEqual('Failed');
        expect(wrapper.vm.getStateLabel('aborted')).toEqual('Aborted');
    });

    it('should show the technical name when no translation exists', async () => {
        const { wrapper } = createWrapper();

        expect(wrapper.vm.getStateLabel('waiting')).toEqual('waiting');
    });
});
