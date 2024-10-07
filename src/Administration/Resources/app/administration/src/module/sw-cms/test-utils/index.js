import { mount } from '@vue/test-utils';

function runGenericCmsTest(component) {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = mount(component);
        expect(wrapper.vm).toBeTruthy();
    });
}

function runCmsBlockRegistryTest(config) {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import(config.import);
    });

    it('registers the component in the CMS block registry', async () => {
        const componentRegistry = Shopware.Component.getComponentRegistry();
        const cmsService = Shopware.Service('cmsService');

        expect(componentRegistry.has(config.component)).toBeTruthy();
        expect(componentRegistry.has(config.preview)).toBeTruthy();

        const blockConfig = cmsService.getCmsBlockConfigByName(config.name);

        expect(blockConfig.component).toBe(config.component);
        expect(blockConfig.previewComponent).toBe(config.preview);
    });
}

function runCmsElementRegistryTest(config) {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import(config.import);
    });

    it('registers the component in the CMS block registry', async () => {
        const componentRegistry = Shopware.Component.getComponentRegistry();
        const cmsService = Shopware.Service('cmsService');

        expect(componentRegistry.has(config.component)).toBeTruthy();

        if (config.preview) {
            expect(componentRegistry.has(config.preview)).toBeTruthy();
        }

        const elementConfig = cmsService.getCmsElementConfigByName(config.name);

        expect(elementConfig.component).toBe(config.component);
        expect(elementConfig.configComponent).toBe(config.config);

        if (config.preview) {
            expect(elementConfig.previewComponent).toBe(config.preview);
        }
    });
}

async function setupCmsEnvironment() {
    await import('src/module/sw-cms/store/cms-page.store');
    await import('src/module/sw-cms/service/cms.service');
    await import('src/module/sw-cms/service/cms-element-favorites.service');
    await import('src/module/sw-cms/mixin/sw-cms-element.mixin');
    await import('src/module/sw-cms/mixin/sw-cms-state.mixin');

    Shopware.State.get('session').currentUser = {
        id: 'admin',
    };
}


/**
 * @private
 * @package buyers-experience
 */
export {
    runGenericCmsTest,
    runCmsBlockRegistryTest,
    runCmsElementRegistryTest,
    setupCmsEnvironment,
};
