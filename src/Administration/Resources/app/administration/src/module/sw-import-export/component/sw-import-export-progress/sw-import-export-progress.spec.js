/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

describe('module/sw-import-export/components/sw-import-export-progress', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-import-export-progress', { sync: true }));
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('button should not be disabled when disableButton is false', async () => {
        const proccessActionButton = wrapper.find('.sw-import-export-progress__start-process-action');

        expect(proccessActionButton.attributes().disabled).toBeTruthy();

        await wrapper.setProps({
            disableButton: false,
        });

        expect(proccessActionButton.attributes().disabled).toBeFalsy();
    });
});
