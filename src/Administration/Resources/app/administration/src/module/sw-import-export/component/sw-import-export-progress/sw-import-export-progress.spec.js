import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-progress';

describe('module/sw-import-export/components/sw-import-export-progress', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-import-export-progress'), {
            stubs: [
                'sw-button'
            ]
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('button should not be disabled when disableButton is false', async () => {
        const proccessActionButton = wrapper.find('.sw-import-export-progress__start-process-action');

        expect(proccessActionButton.attributes().disabled).toBeTruthy();

        await wrapper.setProps({
            disableButton: false
        });

        expect(proccessActionButton.attributes().disabled).toBeFalsy();
    });
});
