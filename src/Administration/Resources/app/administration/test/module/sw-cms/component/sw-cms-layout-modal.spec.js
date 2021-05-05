import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-layout-modal';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-layout-modal'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: jest.fn(() => {
                        return Promise.resolve([]);
                    })
                })
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-simple-search-field': true,
            'sw-loader': true,
            'sw-container': true,
            'sw-button': true
        }
    });
}

describe('module/sw-cms/component/sw-cms-layout-modal', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should search cms pages with criteria filters', async () => {
        await wrapper.setProps({
            cmsPageTypes: ['page', 'landingpage', 'product_list']
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: [
                {
                    type: 'equalsAny',
                    field: 'type',
                    value: 'page|landingpage|product_list'
                }
            ]
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should search cms pages without criteria filters', async () => {
        await wrapper.setProps({
            cmsPageTypes: []
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(expect.objectContaining({
            filters: []
        }));

        expect(wrapper.vm.pageRepository.search).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });
});
