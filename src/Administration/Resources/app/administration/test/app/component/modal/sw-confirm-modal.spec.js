import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/modal/sw-confirm-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';

const swModal = Shopware.Component.build('sw-modal');

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-confirm-modal'), {
        localVue: createLocalVue(),
        stubs: {
            'sw-modal': swModal,
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-loader': true,
            'sw-icon': true
        },
        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            },
        },
        propsData,
    });
}

describe('src/app/component/modal/sw-confirm-modal', () => {
    let wrapper = null;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('emits confirm when confirm button is clicked', async () => {
        wrapper = createWrapper({});

        wrapper.get('.sw-confirm-modal__button-confirm')
            .trigger('click');

        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('emits cancel when cancel button is clicked', async () => {
        wrapper = createWrapper({});

        wrapper.get('.sw-confirm-modal__button-cancel')
            .trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });

    it('emits close when modal is closed', async () => {
        wrapper = createWrapper({});

        wrapper.findComponent(swModal)
            .vm.$emit('modal-close');

        expect(wrapper.emitted('close')).toBeTruthy();
    });

    function expectedValues(confirmButtonVariant, confirmText, cancelText) {
        return {
            confirmButtonVariant,
            confirmText,
            cancelText,
        };
    }

    const typeExpectations = [
        ['confirm', expectedValues('primary', 'confirm', 'cancel')],
        ['yesno', expectedValues('primary', 'yes', 'no')],
        ['delete', expectedValues('danger', 'delete', 'cancel')],
        ['discard', expectedValues('danger', 'discard', 'cancel')],
    ];

    it.each(typeExpectations)('has correct labels for %s', (type, { cancelText, confirmText, confirmButtonVariant }) => {
        wrapper = createWrapper({ type });

        expect(wrapper.get('.sw-confirm-modal__button-cancel').text()).toBe(`global.default.${cancelText}`);
        expect(wrapper.get('.sw-confirm-modal__button-confirm').text()).toBe(`global.default.${confirmText}`);
        expect(wrapper.get('.sw-confirm-modal__button-confirm').classes(`sw-button--${confirmButtonVariant}`)).toBe(true);
    });
});
