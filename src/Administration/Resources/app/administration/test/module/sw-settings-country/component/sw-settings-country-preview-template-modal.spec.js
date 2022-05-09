import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-modal';
import 'src/module/sw-settings-country/component/sw-settings-country-preview-template-modal';

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-settings-country-preview-template-modal'), {
        propsData: {
            previewData: {},
            country: {},
            ...propsData,
        },

        provide: {
            countryAddressService: {
                previewTemplate() {
                    return Promise.resolve('default-formatting-address');
                },

                formattingAddress() {
                    return Promise.resolve('advanced-formatting-address');
                },
            },
            shortcutService: {
                startEventListener() {},
                stopEventListener() {},
            },
        },

        stubs: {
            'sw-button': true,
            'sw-icon': true,
            'sw-modal': Shopware.Component.build('sw-modal'),
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-preview-template-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the default formatting address', async () => {
        const wrapper = createWrapper({
            previewData: {
                defaultBillingAddress: {},
            },
            country: {
                useDefaultAddressFormat: true,
            },
        });

        await wrapper.vm.$nextTick();

        const formattingAddress = wrapper.find('.sw-modal__body span');
        expect(formattingAddress).toBeTruthy();
        expect(formattingAddress.text()).toBe('default-formatting-address');
    });

    it('should render the advance formatting address', async () => {
        const wrapper = createWrapper({
            previewData: {
                defaultBillingAddress: {},
            },
            country: {
                useDefaultAddressFormat: false,
            },
        });
        await wrapper.vm.$nextTick();

        const formattingAddress = wrapper.find('.sw-modal__body span');
        expect(formattingAddress).toBeTruthy();
        expect(formattingAddress.text()).toBe('advanced-formatting-address');
    });
});
