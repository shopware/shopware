import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-config';
import 'src/app/component/base/sw-button';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-extension-config'), {
        localVue,
        propsData: {
            namespace: 'MyExtension'
        },
        stubs: {
            'sw-meteor-page': {
                template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button')
        },
        provide: {
            systemConfigApiService: {
                getValues: () => {
                    return Promise.resolve({
                        'core.store.apiUri': 'https://api.shopware.com',
                        'core.store.licenseHost': 'sw6.test.shopware.in',
                        'core.store.shopSecret': 'very.s3cret',
                        'core.store.shopwareId': 'max@muster.com'
                    });
                }
            }
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-my-extensions-account', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('domain should suffix config', () => {
        expect(wrapper.vm.domain).toBe('MyExtension.config');
    });

    it('Save click success', async () => {
        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.resolve()
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationSuccess).toBeCalledTimes(1);
    });

    it('Save click error', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$refs.systemConfig = {
            saveAll: () => Promise.reject()
        };

        await wrapper.find('.sw-extension-config__save-action').trigger('click');

        expect(wrapper.vm.createNotificationError).toBeCalledTimes(1);
    });
});
