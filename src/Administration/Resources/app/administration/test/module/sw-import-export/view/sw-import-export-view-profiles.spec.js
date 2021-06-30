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

    it('should correctly duplicate the profile even if label does not exist in the current language', async () => {
        wrapper = createWrapper();

        // no profile is selected
        expect(wrapper.vm.selectedProfile).toBe(null);

        // duplicate profile without label
        wrapper.vm.onDuplicateProfile({
            label: undefined,
            translated: {
                label: 'My products'
            }
        });

        // duplicated profile has correct label
        expect(wrapper.vm.selectedProfile.label).toEqual('sw-import-export.profile.copyOfLabel My products');
    });

    it('should save the profile every time in the default language', async () => {
        const clientMock = global.repositoryFactoryMock.clientMock;

        // stub save request
        responses.addResponse({
            method: 'Post',
            url: '/import-export-profile',
            status: 200,
            response: {
                data: []
            }
        });

        wrapper = createWrapper();

        // create selected profile by duplication
        wrapper.vm.onDuplicateProfile({
            label: undefined,
            translated: {
                label: 'My products'
            }
        });

        // reset request history
        clientMock.resetHistory();

        // change context language id to non default
        Shopware.State.commit('context/setApiLanguageId', '1a2b3cNotGood');

        // save profile
        await wrapper.vm.saveSelectedProfile();

        // check if save was called with the correct language id
        expect(clientMock.history.post.length).toBe(1);
        expect(clientMock.history.post[0].headers['sw-language-id']).toEqual('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
    });
});
