import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-introduction';
import 'src/app/component/base/sw-container';

describe('module/sw-sales-channel/component/sw-sales-channel-google-introduction', () => {
    const CreateSalesChannelGoogleIntroductionView = function CreateSalesChannelGoogleIntroductionView() {
        return shallowMount(Shopware.Component.build('sw-sales-channel-google-introduction'), {
            stubs: {
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-card-section': '<div />'
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $device: { onResize: () => {} },
                $route: { name: 'sw.sales.channel.detail.base.step-1' },
                $router: { push: () => {} }
            },
            provide: {
                googleAuthService: {
                    load: () => {},
                    getAuthCode: () => Promise.resolve()
                }
            },
            props: {
                salesChannel: null
            }
        });
    };

    it('should be a vue js component', () => {
        const salesChannelGoogleIntroductionView = new CreateSalesChannelGoogleIntroductionView();

        expect(salesChannelGoogleIntroductionView.isVueInstance()).toBeTruthy();
    });

    it('getAuthCode of googleAuthService should be called when onClickConnect triggered', () => {
        const salesChannelGoogleIntroductionView = new CreateSalesChannelGoogleIntroductionView();
        const spy = jest.spyOn(salesChannelGoogleIntroductionView.vm.googleAuthService, 'getAuthCode');

        salesChannelGoogleIntroductionView.vm.onClickConnect();
        expect(spy).toHaveBeenCalled();
    });
});
