import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/view/sw-import-export-view-profiles';


function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-import-export-view-profiles'), {
        stubs: {
            'sw-card': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-entity-listing': true,
            'sw-import-export-edit-profile-modal': true
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-card-base', () => {
    /** @type Wrapper */
    let wrapper;

    const responses = global.repositoryFactoryMock.responses;

    beforeEach(() => {
        responses.addResponse({
            method: 'Post',
            url: '/search/import-export-profile',
            status: 200,
            response: { data: [] }
        });
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
