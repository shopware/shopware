import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';
import SwExtensionMyAppsErrorPage from 'src/module/sw-extension/component/sw-extension-app-module-error-page';

Shopware.Component.register('sw-extension-app-module-error-page', SwExtensionMyAppsErrorPage);
const routerMock = {
    go: jest.fn(),
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-extension-app-module-error-page'), {
        stubs: {
            'sw-button': await Shopware.Component.build('sw-button'),
        },
        mocks: {
            $router: routerMock,
        },
        attachTo: document.body,
    });
}

describe('src/module/sw-extension/component/sw-extension-app-module-error-page', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('routes you back to the last page', async () => {
        wrapper = await createWrapper();

        const goBackButton = wrapper.get('button');

        expect(goBackButton.text()).toBe('sw-extension.sw-extension-app-module-error-page.error.lblBackButton');

        expect(routerMock.go).not.toHaveBeenCalled();

        await goBackButton.trigger('click');

        expect(routerMock.go).toHaveBeenCalled();
        expect(routerMock.go).toHaveBeenCalledWith(-1);
    });
});
