/**
 * @package checkout
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const swFirstRunWizardWelcomeButtonConfig = [
    {
        key: 'next',
        label: 'sw-first-run-wizard.general.buttonNext',
        position: 'right',
        variant: 'primary',
        action: 'sw.first.run.wizard.index.data-import',
        disabled: false,
    },
];

async function createWrapper(routerViewComponent = 'sw-first-run-wizard-welcome') {
    return mount(await wrapTestComponent('sw-first-run-wizard-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-first-run-wizard-welcome': await wrapTestComponent('sw-first-run-wizard-welcome'),
                'sw-first-run-wizard-mailer-selection': await wrapTestComponent('sw-first-run-wizard-mailer-selection'),
                'sw-first-run-wizard-mailer-local': await wrapTestComponent('sw-first-run-wizard-mailer-local'),
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-loader': true,
                'sw-icon': true,
                'router-view': {
                    template: '<div class="router-view"><slot v-bind="slotBindings"></slot></div>',
                    data() {
                        return {
                            slotBindings: {
                                Component: routerViewComponent,
                            },
                        };
                    },
                },
                'sw-password-field': true,
                'sw-step-display': true,
                'sw-step-item': true,
                'sw-plugin-card': true,
                'sw-select-field': true,
                'router-link': true,
                'sw-help-text': true,
            },
            mocks: {
                $route: { name: 'sw.first.run.wizard.index.welcome' },
            },
            provide: {
                firstRunWizardService: { setFRWStart: () => {} },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
                languagePluginService: {
                    getPlugins: () => Promise.resolve({ items: [] }),
                },
                userService: {
                    getUser: () => Promise.resolve({ data: {} }),
                },
                loginService: {},
                systemConfigApiService: {},
            },
        },
        props: {},
    });
}
/**
 * @package checkout
 */
describe('module/sw-first-run-wizard/component/sw-first-run-wizard-modal', () => {
    beforeAll(() => {
        const responses = global.repositoryFactoryMock.responses;

        responses.addResponse({
            method: 'Post',
            url: '/search/user',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: [],
            },
        });

        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                            appsRequireAppUrl: false,
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                    authToken: {
                        token: 'testToken',
                    },
                },
            },
        });
    });

    beforeEach(() => {
        Shopware.Context.app.firstRunWizard = false;

        Object.defineProperty(window, 'location', {
            writable: true,
            value: { reload: jest.fn() },
        });
    });

    it('stepper has less steps with disabled extension management', async () => {
        const wrapper = await createWrapper();

        expect(Object.keys(wrapper.vm.stepper)).toHaveLength(13);

        Shopware.State.get('context').app.config.settings.disableExtensionManagement = true;

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.stepper)).toHaveLength(8);

        Shopware.State.get('context').app.config.settings.disableExtensionManagement = false;
    });

    it('the default button config should be the config of the sw-first-run-wizard-welcome component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(swFirstRunWizardWelcomeButtonConfig);
    });

    it('the footer should not contain buttons', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(0);
        expect(footerRight.element.children).toHaveLength(1);
        expect(footerRight.findAll('button')).toHaveLength(1);
    });

    it('the button config should have the same config which are emitted by an event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false,
            },
            {
                key: 'two',
                label: 'Two',
                position: 'right',
                variant: null,
                action: 'route.two',
                disabled: false,
            },
            {
                key: 'three',
                label: 'Three',
                position: 'right',
                variant: 'primary',
                action: 'route.three',
                disabled: true,
            },
        ];

        routerViewComponent.vm.$emit('buttons-update', newButtonConfig);

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the footer should have the button config which are emitted by an event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        let footerLeft = wrapper.find('.footer-left');
        let footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(0);
        expect(footerRight.element.children).toHaveLength(1);
        expect(footerRight.findAll('button')).toHaveLength(1);

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false,
            },
            {
                key: 'two',
                label: 'Two',
                position: 'right',
                variant: null,
                action: 'route.two',
                disabled: false,
            },
            {
                key: 'three',
                label: 'Three',
                position: 'right',
                variant: 'primary',
                action: 'route.three',
                disabled: true,
            },
        ];

        await routerViewComponent.vm.$emit('buttons-update', newButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(1);
        expect(footerLeft.findAll('button')).toHaveLength(1);
        expect(footerRight.element.children).toHaveLength(2);
        expect(footerRight.findAll('button')).toHaveLength(2);
    });

    it('the buttonConfig should push a button in the left footer', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false,
            },
        ];

        await routerViewComponent.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(1);
        expect(footerLeft.findAll('button')).toHaveLength(1);
        expect(footerRight.element.children).toHaveLength(0);
    });

    it('the buttonConfig should push a button in the right footer', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'right',
                variant: null,
                action: 'route.one',
                disabled: false,
            },
        ];

        await routerViewComponent.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(0);
        expect(footerRight.element.children).toHaveLength(1);
        expect(footerRight.findAll('button')).toHaveLength(1);
    });

    it('the buttonConfig should overwrite the previous one', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        let footerLeft;
        let footerRight;

        const firstButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'right',
                variant: null,
                action: 'route.one',
                disabled: false,
            },
        ];

        await routerViewComponent.vm.$emit('buttons-update', firstButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(0);
        expect(footerRight.element.children).toHaveLength(1);
        expect(footerRight.findAll('button')).toHaveLength(1);

        const secondButtonConfig = [
            {
                key: 'second',
                label: 'Second',
                position: 'left',
                variant: null,
                action: 'route.two',
                disabled: true,
            },
        ];

        await routerViewComponent.vm.$emit('buttons-update', secondButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element.children).toHaveLength(1);
        expect(footerLeft.findAll('button')).toHaveLength(1);
        expect(footerRight.element.children).toHaveLength(0);
    });

    it('the title should show an warning when not defined', async () => {
        const wrapper = await createWrapper('');
        await flushPromises();

        expect(wrapper.vm.$data.title).toBe('No title defined');
    });

    it('the title should be updated when the router view emits an event', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerViewComponent = wrapper.findComponent('.router-view > .sw-first-run-wizard-modal__component');

        const newTitle = 'fooBar';

        routerViewComponent.vm.$emit('frw-set-title', newTitle);

        expect(wrapper.vm.$data.title).toBe(newTitle);
    });

    it('onButtonClick: should call the redirect function when string', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const spy = jest.spyOn(wrapper.vm, 'redirect');

        expect(spy).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick('foo.bar');

        expect(spy).toHaveBeenCalled();
    });

    it('onButtonClick: should call the callback function', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const callbackFunction = jest.fn();

        expect(callbackFunction).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick(callbackFunction);

        expect(callbackFunction).toHaveBeenCalled();
    });

    it('should not be closable when frw flag is active', async () => {
        Shopware.Context.app.firstRunWizard = true;

        const wrapper = await createWrapper();
        await flushPromises();

        const closeButton = wrapper.find('[aria-label="global.sw-modal.labelClose"]');

        expect(closeButton.exists()).toBe(false);
    });

    it('should be closable when frw flag is not true', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper();
        await flushPromises();
        const closeButton = wrapper.find('[aria-label="global.sw-modal.labelClose"]');

        expect(closeButton.exists()).toBe(true);
    });

    it('should push route to settings page when getting closed', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper();
        await flushPromises();

        const closeButton = wrapper.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(wrapper.vm.$router, 'push');

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
    });

    it('should reload after push route to settings page when getting closed and extension was activated', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onExtensionActivated();
        const closeButton = wrapper.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(wrapper.vm.$router, 'push');

        expect(window.location.reload).not.toHaveBeenCalled();
        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('should not reload after push route to settings page when getting closed and no extension was activated', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper();
        await flushPromises();

        const closeButton = wrapper.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(wrapper.vm.$router, 'push');

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
        expect(window.location.reload).not.toHaveBeenCalled();
    });

    it('should contain all required frw steps', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper('sw-first-run-wizard-welcome');
        await flushPromises();

        const steps = [
            'welcome',
            'data-import',
            'defaults',
            'mailer.selection',
            'mailer.smtp',
            'mailer.local',
            'paypal.info',
            'paypal.credentials',
            'plugins',
            'shopware.account',
            'shopware.domain',
            'store',
            'finish',
        ];

        expect(Object.keys(wrapper.vm.stepper)).toStrictEqual(steps);
    });

    it('should redirect to smtp mailer settings', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper('sw-first-run-wizard-mailer-selection');
        await flushPromises();

        const localOption = wrapper.findAll('.sw-first-run-wizard-mailer-selection__selection').at(1);

        expect(localOption.exists()).toBe(true);
        expect(localOption.find('p').text()).toBe('sw-first-run-wizard.mailerSelection.smtpOption');

        await localOption.trigger('click');
        await wrapper.find('.sw-button--primary').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.first.run.wizard.index.mailer.smtp' });
    });

    it('should redirect to local mailer settings', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const wrapper = await createWrapper('sw-first-run-wizard-mailer-selection');
        await flushPromises();

        const localOption = wrapper.find('.sw-first-run-wizard-mailer-selection__selection');

        expect(localOption.exists()).toBe(true);
        expect(localOption.find('.sw-first-run-wizard-mailer-selection__help-text').attributes('text')).toBe('sw-first-run-wizard.mailerSelection.localOptionHelptext');

        await localOption.trigger('click');
        await wrapper.find('.sw-button--primary').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.first.run.wizard.index.mailer.local' });
    });
});
