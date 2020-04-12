import ImportExportService from 'src/module/sw-import-export/service/importExport.service';
import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-activity';
import 'src/module/sw-import-export/component/sw-import-export-activity-detail-modal';

describe('module/sw-import-export/components/sw-import-export-activity', () => {
    const createWrapper = (options = {}) => {
        const localVue = createLocalVue();

        localVue.filter('date', (date) => date);

        const defaultOptions = {
            localVue,
            stubs: {
                'sw-entity-listing': '<div></div>',
                'sw-import-export-activity-detail-modal': Shopware.Component.build('sw-import-export-activity-detail-modal'),
                'sw-import-export-edit-profile-modal': '<div></div>',
                'sw-modal': '<div></div>'
            },
            mocks: {
                $tc: (translationPath) => translationPath,
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
                }
            }
        };

        const wrapper = shallowMount(
            Shopware.Component.build('sw-import-export-activity'),
            Object.assign(defaultOptions, options)
        );

        return { wrapper, localVue };
    };

    it('should be a Vue.js component', () => {
        const { wrapper } = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should open the activity detail modal', () => {
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

        wrapper.setData({ selectedLog: logEntity });

        const detailModal = wrapper.find('.sw-import-export-activity-detail-modal');

        expect(wrapper.isVueInstance()).toBeTruthy();
        expect(detailModal.isVueInstance()).toBeTruthy();
        expect(detailModal.vm.logEntity).toEqual(logEntity);
    });
});
