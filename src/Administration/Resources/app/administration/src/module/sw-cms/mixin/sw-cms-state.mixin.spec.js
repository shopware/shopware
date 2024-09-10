/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

function createWrapper() {
    return mount({
        template: '<div></div>',
        mixins: [
            Shopware.Mixin.getByName('cms-state'),
        ],
    });
}

const deviceViews = {
    desktop: 'desktop',
    mobile: 'mobile',
};

describe('module/sw-cms/mixin/sw-cms-state.mixin.js', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    afterEach(() => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
    });

    it('properties are properly written to and read from the shared store', () => {
        const wrapper = createWrapper();
        const store = Shopware.Store.get('cmsPageState');

        const block = { id: 'block-1234' };
        wrapper.vm.selectedBlock = block;
        expect(wrapper.vm.selectedBlock).toEqual(block);
        expect(wrapper.vm.selectedBlock).toEqual(store.selectedBlock);

        const section = { id: 'section-1234' };
        wrapper.vm.selectedSection = section;
        expect(wrapper.vm.selectedSection).toEqual(section);
        expect(wrapper.vm.selectedSection).toEqual(store.selectedSection);

        expect(wrapper.vm.currentDeviceView).toEqual(deviceViews.desktop);
        store.setCurrentCmsDeviceView(deviceViews.mobile);
        expect(wrapper.vm.currentDeviceView).toEqual(deviceViews.mobile);

        expect(wrapper.vm.isSystemDefaultLanguage).toBe(true);
        store.setIsSystemDefaultLanguage(false);
        expect(wrapper.vm.isSystemDefaultLanguage).toBe(false);
    });
});
