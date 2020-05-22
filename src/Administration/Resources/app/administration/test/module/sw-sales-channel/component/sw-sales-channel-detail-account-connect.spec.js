import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-detail-account-connect';

describe('src/module/sw-sales-channel/component/sw-sales-channel-detail-account-connect', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(
            Shopware.Component.build('sw-sales-channel-detail-account-connect'),
            {
                propsData: {
                    isGoogleShoppingCreate: false
                },

                stubs: {
                    'sw-button': true
                },

                mocks: {
                    $tc: key => key
                }
            }
        );
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should emit an event when execute onConnectToGoogle()', () => {
        wrapper.vm.onConnectToGoogle();

        expect(wrapper.emitted()).toEqual({ 'on-connect-to-google': [[]] });
    });
});
