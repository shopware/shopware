import { createLocalVue, mount } from '@vue/test-utils';

import 'src/module/sw-sales-channel/component/sw-sales-channel-modal/';
import 'src/module/sw-sales-channel/component/sw-sales-channel-modal-grid/';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    const searchFunction = jest.fn(() => Promise.resolve());

    return {
        searchFunction,
        wrapper: mount(Shopware.Component.build('sw-sales-channel-modal'), {
            localVue,
            stubs: {
                'sw-modal': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-sales-channel-modal-grid': Shopware.Component.build('sw-sales-channel-modal-grid'),
                'sw-loader': true
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: searchFunction
                    })
                }
            }
        })
    };
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-modal/', () => {
    it('should be a Vue.js component', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should request the interface language for the saleschannel', async () => {
        // set the interface language
        Shopware.State.get('session').languageId = 'dutchLanguageId';

        const { searchFunction } = await createWrapper();
        const lastSearchParameters = searchFunction.mock.calls[searchFunction.mock.calls.length - 1];

        expect(lastSearchParameters[1].languageId).toEqual('dutchLanguageId');
    });
});
