import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

const routerMock = {
    go: jest.fn(),
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-app-module-error-page', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-button': await wrapTestComponent('sw-button', {
                        sync: true,
                    }),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'router-link': true,
                    'sw-loader': true,
                },
                mocks: {
                    $router: routerMock,
                },
            },
        },
    );
}

describe('src/module/sw-extension/component/sw-extension-app-module-error-page', () => {
    it('routes you back to the last page', async () => {
        const wrapper = await createWrapper();

        const goBackButton = wrapper.getComponent('.sw-button');

        expect(goBackButton.text()).toBe('sw-extension.sw-extension-app-module-error-page.error.lblBackButton');

        expect(routerMock.go).not.toHaveBeenCalled();

        await goBackButton.trigger('click');

        expect(routerMock.go).toHaveBeenCalled();
        expect(routerMock.go).toHaveBeenCalledWith(-1);
    });
});
