/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper(productSearchConfigs) {
    return mount(await wrapTestComponent('sw-settings-search-view-general', { sync: true }), {
        props: { productSearchConfigs },
        global: {
            stubs: {
                'sw-settings-search-search-behaviour': true,
                'sw-settings-search-excluded-search-terms': true,
                'sw-settings-search-searchable-content': {
                    name: 'sw-settings-search-searchable-content',
                    props: ['searchConfigId', 'productSearchConfigs'],
                    template: '<div></div>',
                },
            },
        },
    });
}

describe('module/sw-settings-search/view/sw-settings-search-view-general', () => {
    it('should pass the id of already loaded search configs to the searchable content', async () => {
        const wrapper = await createWrapper({ id: 'search-config-id' });

        expect(wrapper.getComponent({ name: 'sw-settings-search-searchable-content' }).props('searchConfigId')).toBe(
            'search-config-id',
        );
    });

    it('should pass the id of search configs loaded after mount to the searchable content', async () => {
        const wrapper = await createWrapper({ andLogic: true });
        await wrapper.setProps({ productSearchConfigs: { id: 'search-config-id' } });

        expect(wrapper.getComponent({ name: 'sw-settings-search-searchable-content' }).props('searchConfigId')).toBe(
            'search-config-id',
        );
    });
});
