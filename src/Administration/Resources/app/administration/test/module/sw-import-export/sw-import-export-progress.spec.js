import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-progress';

describe('components/sw-import-export-progress', () => {
    let wrapper;
    let localVue;

    beforeEach(() => {
        localVue = createLocalVue();

        wrapper = shallowMount(Shopware.Component.build('sw-import-export-progress'), {
            localVue,
            stubs: [
                'sw-progress-bar', 'sw-button'
            ],
            mocks: {
                $tc: (translationPath) => translationPath
            },
            provide: {
                importExport: { getDownloadUrl: () => { return ''; } }
            }
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });
});
