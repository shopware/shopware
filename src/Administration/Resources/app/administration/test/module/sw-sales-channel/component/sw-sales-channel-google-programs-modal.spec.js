import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-programs-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-container';

describe('module/sw-sales-channel/component/sw-sales-channel-google-programs-modal', () => {
    const CreateSalesChannelGoogleProgramsModal = function CreateSalesChannelGoogleOauthModal() {
        return shallowMount(Shopware.Component.build('sw-sales-channel-google-programs-modal'), {
            stubs: {
                'sw-modal': Shopware.Component.build('sw-modal'),
                'sw-container': Shopware.Component.build('sw-container'),
                'router-view': '<div id="router-view"></div>',
                'sw-icon': '<div></div>',
                'sw-button': '<div></div>',
                'sw-button-process': '<div></div>'
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $route: { name: 'sw.sales.channel.detail.base.step-1' },
                $router: { push: () => {} }
            },
            provide: {
                shortcutService: { stopEventListener: () => {} }
            },
            props: {
                salesChannel: null
            }
        });
    };

    it('should be a vue js component', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();

        expect(salesChannelGoogleProgramsModal.isVueInstance()).toBeTruthy();
    });

    it('the button config should have the same config which are emitted by an event', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();
        const routerView = salesChannelGoogleProgramsModal.find('#router-view');

        expect(salesChannelGoogleProgramsModal.vm.$data.buttonConfig).toStrictEqual({});

        const newButtonConfig =
            {
                left: {
                    label: 'Back',
                    variant: null,
                    action: 'route.one',
                    disabled: false
                },
                right: {
                    label: 'Next',
                    variant: 'primary',
                    action: 'route.two',
                    disabled: false
                }
            };

        routerView.vm.$emit('buttons-update', newButtonConfig);

        expect(salesChannelGoogleProgramsModal.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the button right should be sw-button-process if button right config has processFinish as a function', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();
        const routerView = salesChannelGoogleProgramsModal.find('#router-view');

        let buttonProcessRight;
        let buttonRight;

        const firstButtonConfig =
            {
                left: {
                    label: 'Back',
                    variant: null,
                    action: 'route.one',
                    disabled: false
                },

                right: {
                    label: 'Next',
                    variant: 'primary',
                    action: 'route.two',
                    disabled: false,
                    isLoading: false,
                    isSaveSuccessful: false,
                    processFinish: jest.fn()
                }
            };

        routerView.vm.$emit('buttons-update', firstButtonConfig);

        buttonProcessRight = salesChannelGoogleProgramsModal.find('.sw-sales-channel-google-programs-modal__button-process-right');
        buttonRight = salesChannelGoogleProgramsModal.find('.sw-sales-channel-google-programs-modal__button-right');

        expect(buttonProcessRight.exists()).toBeTruthy();
        expect(buttonRight.exists()).toBeFalsy();

        const secondButtonConfig =
            {
                left: {
                    label: 'Back',
                    variant: null,
                    action: 'route.one',
                    disabled: false
                },

                right: {
                    label: 'Next',
                    variant: 'primary',
                    action: 'route.two',
                    disabled: false,
                    isLoading: false,
                    isSaveSuccessful: false,
                    processFinish: null
                }
            };

        routerView.vm.$emit('buttons-update', secondButtonConfig);
        buttonProcessRight = salesChannelGoogleProgramsModal.find('.sw-sales-channel-google-programs-modal__button-process-right');
        buttonRight = salesChannelGoogleProgramsModal.find('.sw-sales-channel-google-programs-modal__button-right');

        expect(buttonProcessRight.exists()).toBeFalsy();
        expect(buttonRight.exists()).toBeTruthy();
    });

    it('number of step item should equal stepper length', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();

        const stepper = {
            'step-1': {
                name: 'sw.sales.channel.detail.base.step-1',
                navigationIndex: 0
            },
            'step-2': {
                name: 'sw.sales.channel.detail.base.step-2',
                navigationIndex: 1
            }
        };

        salesChannelGoogleProgramsModal.vm.$data.stepper = stepper;

        const stepItems = salesChannelGoogleProgramsModal.findAll('.sw-sales-channel-google-programs-modal__step-item');
        expect(stepItems.length).toBe(2);
    });

    it('step item should include is--active class when its navigationIndex equals currentStep navigationIndex', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();
        const getActiveStyle = jest.spyOn(salesChannelGoogleProgramsModal.vm, 'getActiveStyle');

        salesChannelGoogleProgramsModal.vm.$data.currentStep = {
            name: 'sw.sales.channel.detail.base.step-2',
            navigationIndex: 1
        };

        const firstItem = {
            name: 'sw.sales.channel.detail.base.step-2',
            navigationIndex: 1
        };

        const secondItem = {
            name: 'sw.sales.channel.detail.base.step-1',
            navigationIndex: 0
        };

        expect(getActiveStyle(firstItem)).toStrictEqual({ 'is--active': true });
        expect(getActiveStyle(secondItem)).toStrictEqual({ 'is--active': false });
    });

    it('item matched with current step should have is--active style', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();

        const stepper = {
            'step-1': {
                name: 'sw.sales.channel.detail.base.step-1',
                navigationIndex: 0
            },
            'step-2': {
                name: 'sw.sales.channel.detail.base.step-2',
                navigationIndex: 1
            }
        };

        salesChannelGoogleProgramsModal.vm.$data.stepper = stepper;
        salesChannelGoogleProgramsModal.vm.$data.currentStep = {
            name: 'sw.sales.channel.detail.base.step-2',
            navigationIndex: 1
        };

        const stepItems = salesChannelGoogleProgramsModal.findAll('.sw-sales-channel-google-programs-modal__step-item');
        const firstItem = stepItems.at(0);
        const secondItem = stepItems.at(1);

        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).toContain('is--active');
    });

    it('onButtonClick: should call the redirect function when button action is a string', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();
        const spy = jest.spyOn(salesChannelGoogleProgramsModal.vm, 'redirect');

        expect(spy).not.toHaveBeenCalled();

        salesChannelGoogleProgramsModal.vm.onButtonClick('route.one');
        expect(spy).toHaveBeenCalled();
    });

    it('onButtonClick: should call the callback function when button action is a function', () => {
        const salesChannelGoogleProgramsModal = new CreateSalesChannelGoogleProgramsModal();
        const callbackFunction = jest.fn();

        expect(callbackFunction).not.toHaveBeenCalled();

        salesChannelGoogleProgramsModal.vm.onButtonClick(callbackFunction);
        expect(callbackFunction).toHaveBeenCalled();
    });
});
