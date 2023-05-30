/**
 * @package sales-channel
 */

import { createLocalVue, mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import swSalesChannelModal from 'src/module/sw-sales-channel/component/sw-sales-channel-modal/';
import swSalesChannelModalGrid from 'src/module/sw-sales-channel/component/sw-sales-channel-modal-grid/';

Shopware.Component.register('sw-sales-channel-modal', swSalesChannelModal);
Shopware.Component.register('sw-sales-channel-modal-grid', swSalesChannelModalGrid);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const searchFunction = jest.fn(() => Promise.resolve(new EntityCollection(
        '',
        '',
        Shopware.Context.api,
        null,
        [],
        0,
    )));

    return {
        searchFunction,
        wrapper: mount(await Shopware.Component.build('sw-sales-channel-modal'), {
            localVue,
            stubs: {
                'sw-modal': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-sales-channel-modal-grid': await Shopware.Component.build('sw-sales-channel-modal-grid'),
                'sw-sales-channel-modal-detail': true,
                'sw-loader': true,
                'sw-grid': true,
                'sw-grid-column': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: searchFunction,
                    }),
                },
            },
        }),
    };
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-modal/', () => {
    it('should be a Vue.js component', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.setData({
            detailType: 'foo',
        });
        await flushPromises();


        expect(wrapper.vm).toBeTruthy();
    });

    it('should request the interface language for the saleschannel', async () => {
        // set the interface language
        Shopware.State.get('session').languageId = 'dutchLanguageId';

        const { searchFunction, wrapper } = await createWrapper();
        await wrapper.setData({
            detailType: 'foo',
        });
        await flushPromises();

        const lastSearchParameters = searchFunction.mock.calls[searchFunction.mock.calls.length - 1];

        expect(lastSearchParameters[1].languageId).toBe('dutchLanguageId');
    });
});
