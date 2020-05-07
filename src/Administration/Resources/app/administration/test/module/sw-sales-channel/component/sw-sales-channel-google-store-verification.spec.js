import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-store-verification';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-icon';
import 'src/app/component/base/sw-button';

describe('module/sw-sales-channel/component/sw-sales-channel-google-store-verification', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-sales-channel-google-store-verification'), {
            stubs: {
                'sw-label': Shopware.Component.build('sw-label'),
                'sw-icon': Shopware.Component.build('sw-icon'),
                'sw-sales-channel-detail-protect-link': true,
                'sw-button': true,
                'icons-small-default-checkmark-line-medium': true,
                'icons-small-default-x-line-medium': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-4' },
                $router: { push: () => {} }
            },
            props: {
                salesChannel: null
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should render number of item which equals total store verification items length', async () => {
        wrapper.setData({
            items: [
                {
                    status: 'success',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicyUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicy'
                },
                {
                    status: 'success',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textAccurateContactUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textAccurateContact'
                },
                {
                    status: 'success',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textSecureCheckoutProcessUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textSecureCheckoutProcess'
                },
                {
                    status: 'danger',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textReturPolicyUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textReturPolicy'
                },
                {
                    status: 'success',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textBillingTermsUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textBillingTerms'
                },
                {
                    status: 'success',
                    errorLink: 'sw-sales-channel.modalGooglePrograms.step-4.textCompleteUrl',
                    description: 'sw-sales-channel.modalGooglePrograms.step-4.textComplete'
                }
            ]
        });

        await wrapper.vm.$nextTick();
        const verifiationItems = wrapper.findAll('.sw-sales-channel-google-store-verification__checked-item');
        expect(verifiationItems.length).toEqual(wrapper.vm.items.length);
    });
});
