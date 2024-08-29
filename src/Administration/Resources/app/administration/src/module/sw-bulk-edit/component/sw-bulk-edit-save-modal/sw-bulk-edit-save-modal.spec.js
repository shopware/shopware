/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

const modalConfirmButtonConfig = [
    {
        key: 'cancel',
        label: 'global.sw-modal.labelClose',
        position: 'left',
        action: '',
        disabled: false,
    },
    {
        key: 'next',
        label: 'sw-bulk-edit.modal.confirm.buttons.applyChanges',
        position: 'right',
        variant: 'primary',
        action: 'process',
        disabled: false,
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-save-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-icon': {
                    template: '<div class="sw-icon" />',
                },
                'router-view': {
                    template: '<div class="router-view"><slot v-bind="slotBindings"></slot></div>',
                    data() {
                        return {
                            slotBindings: {
                                Component: 'sw-bulk-edit-save-modal-confirm',
                            },
                        };
                    },
                },
                'sw-bulk-edit-save-modal-confirm': await wrapTestComponent('sw-bulk-edit-save-modal-confirm'),
                'sw-loader': true,
                'sw-switch-field': true,
                'sw-alert': true,
                'router-link': true,
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
        },
        props: {
            itemTotal: 1,
            isLoading: false,
            processStatus: '',
            bulkEditData: {},
        },
    });
}

describe('src/module/sw-bulk-edit/modal/sw-bulk-edit-save-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('the default button config should be the bulk-edit-save-modal-confirm button config', async () => {
        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(modalConfirmButtonConfig);
    });

    it('the footer should contain two buttons', async () => {
        const footerLeft = wrapper.findAll('.footer-left > button');
        const footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(1);
        expect(footerRight).toHaveLength(1);
    });

    it('the button config should have the same config which are emitted by an event', async () => {
        const modalComponent = await wrapper.findComponent('.sw-bulk-edit-save-modal__component');

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

        modalComponent.vm.$emit('buttons-update', newButtonConfig);

        expect(wrapper.vm.$data.buttonConfig).toStrictEqual(newButtonConfig);
    });

    it('the footer should have the button config which are emitted by an event', async () => {
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');

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

        await modalComponent.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.findAll('.footer-left > button');
        const footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(1);
        expect(footerRight).toHaveLength(2);
    });

    it('the buttonConfig should push a button in the left footer', async () => {
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');

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

        await modalComponent.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.findAll('.footer-left > button');
        const footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(1);
        expect(footerRight).toHaveLength(0);
    });

    it('the buttonConfig should push a button in the right footer', async () => {
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');

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

        await modalComponent.vm.$emit('buttons-update', newButtonConfig);

        const footerLeft = wrapper.findAll('.footer-left > button');
        const footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(0);
        expect(footerRight).toHaveLength(1);
    });

    it('the buttonConfig should overwrite the previous one', async () => {
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');
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

        await modalComponent.vm.$emit('buttons-update', firstButtonConfig);

        footerLeft = wrapper.findAll('.footer-left > button');
        footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(0);
        expect(footerRight).toHaveLength(1);

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

        await modalComponent.vm.$emit('buttons-update', secondButtonConfig);

        footerLeft = wrapper.findAll('.footer-left > button');
        footerRight = wrapper.findAll('.footer-right > button');

        expect(footerLeft).toHaveLength(1);
        expect(footerRight).toHaveLength(0);
    });

    it('the title should be updated when the router view emits an event', async () => {
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');

        const newTitle = 'fooBar';

        modalComponent.vm.$emit('title-set', newTitle);

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
        const modalComponent = wrapper.findComponent('.sw-bulk-edit-save-modal__component');
        modalComponent.vm.$emit('changes-apply');

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
