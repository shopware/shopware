/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import swBulkEditSaveModal from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';

Shopware.Component.register('sw-bulk-edit-save-modal', swBulkEditSaveModal);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-bulk-edit-save-modal'), {
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-icon': {
                template: '<div />',
            },
            'router-view': {
                template: '<div id="router-view" />',
            },
        },
        props: {
            itemTotal: 1,
            isLoading: false,
            processStatus: '',
            bulkEditData: {},
        },
        mocks: {
            $route: { name: 'sw.bulk.edit.product.save.confirm' },
        },
        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {},
            },
            feature: {
                isActive: () => true,
            },
        },
    });
}

describe('src/module/sw-bulk-edit/modal/sw-bulk-edit-save-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
    });

    it('the default button config should be empty', async () => {
        expect(wrapper.vm.$data.buttonConfig).toStrictEqual([]);
    });

    it('the footer should not contain buttons', async () => {
        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the button config should have the same config which are emitted by an event', async () => {
        const routerView = wrapper.find('#router-view');

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual([]);

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

        routerView.vm.$emit('buttons-update', newButtonConfig);

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the footer should have the button config which are emitted by an event', async () => {
        const routerView = wrapper.find('#router-view');

        let footerLeft = wrapper.find('.footer-left');
        let footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();

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

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();
    });

    it('the buttonConfig should push a button in the left footer', async () => {
        const routerView = wrapper.find('#router-view');

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

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the buttonConfig should push a button in the right footer', async () => {
        const routerView = wrapper.find('#router-view');

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

        await routerView.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.find('.footer-left');
        const footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();
    });

    it('the buttonConfig should overwrite the previous one', async () => {
        const routerView = wrapper.find('#router-view');
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

        await routerView.vm.$emit('buttons-update', firstButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).toBeEmptyDOMElement();
        expect(footerRight.element).not.toBeEmptyDOMElement();

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

        await routerView.vm.$emit('buttons-update', secondButtonConfig);

        footerLeft = wrapper.find('.footer-left');
        footerRight = wrapper.find('.footer-right');

        expect(footerLeft.element).not.toBeEmptyDOMElement();
        expect(footerRight.element).toBeEmptyDOMElement();
    });

    it('the title should be updated when the router view emits an event', async () => {
        const routerView = wrapper.find('#router-view');

        const newTitle = 'fooBar';

        routerView.vm.$emit('title-set', newTitle);

        expect(wrapper.vm.$data.title).toBe(newTitle);
    });

    it('onButtonClick: should call the redirect function when string', async () => {
        const spy = jest.spyOn(wrapper.vm, 'redirect');

        expect(spy).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick('foo.bar');

        expect(spy).toHaveBeenCalled();
    });

    it('onButtonClick: should call the callback function', async () => {
        const callbackFunction = jest.fn();

        expect(callbackFunction).not.toHaveBeenCalled();

        wrapper.vm.onButtonClick(callbackFunction);

        expect(callbackFunction).toHaveBeenCalled();
    });

    it('should emit bulk save event', async () => {
        const routerView = wrapper.find('#router-view');

        routerView.vm.$emit('changes-apply');

        expect(wrapper.emitted()['bulk-save']).toBeTruthy();
    });

    it('should add event listeners after component created', async () => {
        wrapper.vm.addEventListeners = jest.fn();
        wrapper.vm.createdComponent();

        expect(wrapper.vm.addEventListeners).toHaveBeenCalledTimes(1);
        wrapper.vm.addEventListeners.mockRestore();
    });

    it('should remove event listeners before component destroyed', async () => {
        wrapper.vm.removeEventListeners = jest.fn();
        wrapper.vm.beforeDestroyComponent();

        expect(wrapper.vm.removeEventListeners).toHaveBeenCalledTimes(1);
        wrapper.vm.removeEventListeners.mockRestore();
    });

    it('should be able to listen to beforeunload event', async () => {
        await wrapper.setProps({ isLoading: false });
        expect(
            wrapper.vm.beforeUnloadListener({ preventDefault: () => {}, returnValue: '' }),
        ).toBe('');

        await wrapper.setProps({ isLoading: true });
        expect(
            wrapper.vm.beforeUnloadListener({ preventDefault: () => {}, returnValue: '' }),
        ).toBe('sw-bulk-edit.modal.messageBeforeTabLeave');
    });
});
