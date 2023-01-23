/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import swFirstRunWizardModal from 'src/module/sw-first-run-wizard/component/sw-first-run-wizard-modal';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-container';

Shopware.Component.register('sw-first-run-wizard-modal', swFirstRunWizardModal);

/**
 * @package merchant-services
 */
describe('module/sw-first-run-wizard/component/sw-first-run-wizard-modal', () => {
    const CreateFirstRunWizardModal = async function CreateFirstRunWizardModal() {
        return shallowMount(await Shopware.Component.build('sw-first-run-wizard-modal'), {
            stubs: {
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-container': await Shopware.Component.build('sw-container'),
                'sw-loader': true,
                'sw-icon': {
                    template: '<div />'
                },
                'router-view': {
                    template: '<div id="router-view" />'
                },
                'sw-button': {
                    template: '<div />'
                }
            },
            mocks: {
                $route: { name: 'sw.first.run.wizard.index.welcome' }
            },
            provide: {
                firstRunWizardService: { setFRWStart: () => {} },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {}
                }
            },
            props: {}
        });
    };

    beforeEach(() => {
        Shopware.Context.app.firstRunWizard = false;

        Object.defineProperty(window, 'location', {
            writable: true,
            value: { reload: jest.fn() },
        });
    });

    it('should be a vue js component', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();

        expect(firstRunWizardModal.vm).toBeTruthy();
    });

    it('the default button config should be empty', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();

        expect(firstRunWizardModal.vm.$data.buttonConfig).toStrictEqual([]);
    });

    it('the footer should not contain buttons', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();

        const footerLeft = firstRunWizardModal.get('.footer-left');
        const footerRight = firstRunWizardModal.get('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the button config should have the same config which are emitted by an event', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');

        expect(firstRunWizardModal.vm.$data.buttonConfig).toStrictEqual([]);

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false
            },
            {
                key: 'two',
                label: 'Two',
                position: 'right',
                variant: null,
                action: 'route.two',
                disabled: false
            },
            {
                key: 'three',
                label: 'Three',
                position: 'right',
                variant: 'primary',
                action: 'route.three',
                disabled: true
            }
        ];

        routerView.vm.$emit('buttons-update', newButtonConfig);

        expect(firstRunWizardModal.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the footer should have the button config which are emitted by an event', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');

        let footerLeft = firstRunWizardModal.find('.footer-left');
        let footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false
            },
            {
                key: 'two',
                label: 'Two',
                position: 'right',
                variant: null,
                action: 'route.two',
                disabled: false
            },
            {
                key: 'three',
                label: 'Three',
                position: 'right',
                variant: 'primary',
                action: 'route.three',
                disabled: true
            }
        ];

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        footerLeft = firstRunWizardModal.find('.footer-left');
        footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();
    });

    it('the buttonConfig should push a button in the left footer', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'left',
                variant: null,
                action: 'route.one',
                disabled: false
            }
        ];

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = firstRunWizardModal.find('.footer-left');
        const footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the buttonConfig should push a button in the right footer', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');

        const newButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'right',
                variant: null,
                action: 'route.one',
                disabled: false
            }
        ];

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = firstRunWizardModal.find('.footer-left');
        const footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();
    });

    it('the buttonConfig should overwrite the previous one', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');
        let footerLeft;
        let footerRight;

        const firstButtonConfig = [
            {
                key: 'one',
                label: 'One',
                position: 'right',
                variant: null,
                action: 'route.one',
                disabled: false
            }
        ];

        await routerView.vm.$emit('buttons-update', firstButtonConfig);

        footerLeft = firstRunWizardModal.find('.footer-left');
        footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();

        const secondButtonConfig = [
            {
                key: 'second',
                label: 'Second',
                position: 'left',
                variant: null,
                action: 'route.two',
                disabled: true
            }
        ];

        await routerView.vm.$emit('buttons-update', secondButtonConfig);

        footerLeft = firstRunWizardModal.find('.footer-left');
        footerRight = firstRunWizardModal.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the title should show an warning when not defined', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();

        expect(firstRunWizardModal.vm.$data.title).toBe('No title defined');
    });

    it('the title should be updated when the router view emits an event', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const routerView = firstRunWizardModal.find('#router-view');

        const newTitle = 'fooBar';

        routerView.vm.$emit('frw-set-title', newTitle);

        expect(firstRunWizardModal.vm.$data.title).toBe(newTitle);
    });

    it('onButtonClick: should call the redirect function when string', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const spy = jest.spyOn(firstRunWizardModal.vm, 'redirect');

        expect(spy).not.toHaveBeenCalled();

        firstRunWizardModal.vm.onButtonClick('foo.bar');

        expect(spy).toHaveBeenCalled();
    });

    it('onButtonClick: should call the callback function', async () => {
        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const callbackFunction = jest.fn();

        expect(callbackFunction).not.toHaveBeenCalled();

        firstRunWizardModal.vm.onButtonClick(callbackFunction);

        expect(callbackFunction).toHaveBeenCalled();
    });

    it('should not be closable when frw flag is active', async () => {
        Shopware.Context.app.firstRunWizard = true;

        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const closeButton = firstRunWizardModal.find('[aria-label="global.sw-modal.labelClose"]');

        expect(closeButton.exists()).toBe(false);
    });

    it('should be closable when frw flag is not true', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const closeButton = firstRunWizardModal.find('[aria-label="global.sw-modal.labelClose"]');

        expect(closeButton.exists()).toBe(true);
    });

    it('should push route to settings page when getting closed', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const closeButton = firstRunWizardModal.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(firstRunWizardModal.vm.$router, 'push');

        expect(firstRunWizardModal.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');

        expect(firstRunWizardModal.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
    });

    it('should reload after push route to settings page when getting closed and extension was activated', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        firstRunWizardModal.vm.onExtensionActivated();
        const closeButton = firstRunWizardModal.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(firstRunWizardModal.vm.$router, 'push');

        expect(window.location.reload).not.toHaveBeenCalled();
        expect(firstRunWizardModal.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');
        await flushPromises();

        expect(firstRunWizardModal.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('should not reload after push route to settings page when getting closed and no extension was activated', async () => {
        Shopware.Context.app.firstRunWizard = false;

        const firstRunWizardModal = await new CreateFirstRunWizardModal();
        const closeButton = firstRunWizardModal.find('[aria-label="global.sw-modal.labelClose"]');

        jest.spyOn(firstRunWizardModal.vm.$router, 'push');

        expect(firstRunWizardModal.vm.$router.push).not.toHaveBeenCalled();

        await closeButton.trigger('click');
        await flushPromises();

        expect(firstRunWizardModal.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.index.system' });
        expect(window.location.reload).not.toHaveBeenCalled();
    });
});
