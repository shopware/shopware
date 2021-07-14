import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-profile/view/sw-profile-index-search-preferences';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-profile-index-search-preferences'), {
        localVue
    });
}

describe('src/module/sw-profile/view/sw-profile-index-search-preferences', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});
