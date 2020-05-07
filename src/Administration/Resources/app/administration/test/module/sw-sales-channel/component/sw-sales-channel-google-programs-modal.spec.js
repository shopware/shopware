import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-google-programs-modal';
import 'src/app/component/base/sw-modal';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';

Shopware.State.registerModule('swSalesChannel', state);

const createWrapper = () => {
    return shallowMount(Shopware.Component.build('sw-sales-channel-google-programs-modal'), {
        store: Shopware.State._store,
        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'router-view': '<div id="router-view"></div>',
            'sw-icon': true,
            'sw-button': true,
            'sw-button-process': true
        },
        mocks: {
            $tc: (translationPath) => translationPath,
            $route: { name: 'sw.sales.channel.detail.base.step-1' },
            $router: { push: () => {} }
        },
        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        },
        propsData: {
            salesChannel: {
                googleShoppingAccount: {}
            }
        }
    });
};

describe('module/sw-sales-channel/component/sw-sales-channel-google-programs-modal', () => {
    it('the button config should have the same config which are emitted by an event', () => {
        const wrapper = createWrapper();
        const routerView = wrapper.find('#router-view');

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual({});

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

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the button right should be sw-button-process if button right config has processFinish as a function', () => {
        const wrapper = createWrapper();
        const routerView = wrapper.find('#router-view');

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

        buttonProcessRight = wrapper.find('.sw-sales-channel-google-programs-modal__button-process-right');
        buttonRight = wrapper.find('.sw-sales-channel-google-programs-modal__button-right');

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
                    isProcessSuccessful: false,
                    processFinish: null
                }
            };

        routerView.vm.$emit('buttons-update', secondButtonConfig);
        buttonProcessRight = wrapper.find('.sw-sales-channel-google-programs-modal__button-process-right');
        buttonRight = wrapper.find('.sw-sales-channel-google-programs-modal__button-right');

        expect(buttonProcessRight.exists()).toBeFalsy();
        expect(buttonRight.exists()).toBeTruthy();
    });

    it('number of step item should equal stepper length', () => {
        const wrapper = createWrapper();

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

        wrapper.vm.$data.stepper = stepper;

        const stepItems = wrapper.findAll('.sw-sales-channel-google-programs-modal__step-item');
        expect(stepItems.length).toBe(2);
    });

    it('step item should include is--active class when its navigationIndex equals currentStep navigationIndex', () => {
        const wrapper = createWrapper();

        const getActiveStyle = jest.spyOn(wrapper.vm, 'getActiveStyle');

        const firstItem = {
            name: 'sw.sales.channel.detail.base.step-2',
            navigationIndex: 1
        };

        const secondItem = {
            name: 'sw.sales.channel.detail.base.step-1',
            navigationIndex: 0
        };

        wrapper.vm.$data.currentStep = firstItem;

        expect(getActiveStyle(firstItem)).toStrictEqual({ 'is--active': true });
        expect(getActiveStyle(secondItem)).toStrictEqual({ 'is--active': false });
    });

    it('item matched with current step should have is--active style', () => {
        const wrapper = createWrapper();

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

        wrapper.vm.$data.stepper = stepper;
        wrapper.vm.$data.currentStep = stepper['step-2'];

        const stepItems = wrapper.findAll('.sw-sales-channel-google-programs-modal__step-item');
        const firstItem = stepItems.at(0);
        const secondItem = stepItems.at(1);

        expect(firstItem.classes()).not.toContain('is--active');
        expect(secondItem.classes()).toContain('is--active');
    });

    it('onButtonClick: should call the redirect function when button action is a string', () => {
        const wrapper = createWrapper();
        const spy = jest.spyOn(wrapper.vm, 'redirect');

        expect(spy).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick('route.one');
        expect(spy).toHaveBeenCalled();
    });

    it('onButtonClick: should call the callback function when button action is a function', () => {
        const wrapper = createWrapper();
        const callbackFunction = jest.fn();

        expect(callbackFunction).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick(callbackFunction);
        expect(callbackFunction).toHaveBeenCalled();
    });

    it('should get correct step', () => {
        const wrapper = createWrapper();
        let validStep;

        const stepper = {
            'step-1': {
                name: 'sw.sales.channel.detail.base.step-1',
                navigationIndex: 0
            },
            'step-2': {
                name: 'sw.sales.channel.detail.base.step-2',
                navigationIndex: 1
            },
            'step-3': {
                name: 'sw.sales.channel.detail.base.step-3',
                navigationIndex: 2
            },
            'step-4': {
                name: 'sw.sales.channel.detail.base.step-4',
                navigationIndex: 3
            }
        };

        wrapper.vm.$data.stepper = stepper;

        Shopware.State.commit('swSalesChannel/setGoogleShoppingAccount', null);

        validStep = wrapper.vm.getCorrectStep('step-3');
        expect(validStep).toEqual('step-1');

        Shopware.State.commit('swSalesChannel/setGoogleShoppingAccount', { name: 'John Doe' });
        validStep = wrapper.vm.getCorrectStep('step-2');
        expect(validStep).toEqual('step-2');
    });

    it('redirect should be called when step is different with valid step', () => {
        const wrapper = createWrapper();
        const spy = jest.spyOn(wrapper.vm, 'redirect');

        const stepper = {
            'step-1': {
                name: 'sw.sales.channel.detail.base.step-1',
                navigationIndex: 0
            },
            'step-2': {
                name: 'sw.sales.channel.detail.base.step-2',
                navigationIndex: 1
            },
            'step-3': {
                name: 'sw.sales.channel.detail.base.step-3',
                navigationIndex: 2
            }
        };

        wrapper.vm.$data.stepper = stepper;
        Shopware.State.commit('swSalesChannel/setGoogleShoppingAccount', null);
        wrapper.vm.checkStep('step-3');

        expect(spy).toHaveBeenCalled();
    });

    it('redirect should not be called when step is similar to valid step', () => {
        const wrapper = createWrapper();
        const spy = jest.spyOn(wrapper.vm, 'redirect');

        const stepper = {
            'step-1': {
                name: 'sw.sales.channel.detail.base.step-1',
                navigationIndex: 0
            },
            'step-2': {
                name: 'sw.sales.channel.detail.base.step-2',
                navigationIndex: 1
            },
            'step-3': {
                name: 'sw.sales.channel.detail.base.step-3',
                navigationIndex: 2
            }
        };

        wrapper.vm.$data.stepper = stepper;
        Shopware.State.commit('swSalesChannel/setGoogleShoppingAccount', { name: 'John Doe' });
        wrapper.vm.checkStep('step-3');

        expect(spy).not.toHaveBeenCalled();
    });
});
