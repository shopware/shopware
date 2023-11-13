/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils';
import swImportExportProgress from 'src/module/sw-import-export/component/sw-import-export-progress';

Shopware.Component.register('sw-import-export-progress', swImportExportProgress);

describe('module/sw-import-export/components/sw-import-export-progress', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-import-export-progress'), {
            stubs: [
                'sw-button',
            ],
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
            disableButton: false,
        });

        expect(proccessActionButton.attributes().disabled).toBeFalsy();
    });
});
