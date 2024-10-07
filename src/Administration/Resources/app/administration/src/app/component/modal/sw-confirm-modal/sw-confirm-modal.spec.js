/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

import 'src/app/component/modal/sw-confirm-modal';
import 'src/app/component/base/sw-modal';

describe('src/app/component/modal/sw-confirm-modal', () => {
    let wrapper = null;

    async function createWrapper(props = {}) {
        return mount(await wrapTestComponent('sw-confirm-modal', { sync: true }), {
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                    'sw-loader': true,
                    'sw-icon': true,
                    'router-link': true,
                },
                provide: {
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
            props,
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.unmount();
    });

    it('emits confirm when confirm button is clicked', async () => {
        wrapper = await createWrapper({});

        await wrapper.get('.sw-confirm-modal__button-confirm').trigger('click');

        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('emits cancel when cancel button is clicked', async () => {
        wrapper = await createWrapper({});

        await wrapper.get('.sw-confirm-modal__button-cancel').trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });

    it('emits close when modal is closed', async () => {
        wrapper = await createWrapper({});

        await wrapper.find('.sw-modal__close').trigger('click');
        await flushPromises();

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
        [
            'confirm',
            expectedValues('primary', 'confirm', 'cancel'),
        ],
        [
            'yesno',
            expectedValues('primary', 'yes', 'no'),
        ],
        [
            'delete',
            expectedValues('danger', 'delete', 'cancel'),
        ],
        [
            'discard',
            expectedValues('danger', 'discard', 'cancel'),
        ],
    ];

    it.each(typeExpectations)(
        'has correct labels for %s',
        async (type, { cancelText, confirmText, confirmButtonVariant }) => {
            wrapper = await createWrapper({ type });

            expect(wrapper.get('.sw-confirm-modal__button-cancel').text()).toBe(`global.default.${cancelText}`);
            expect(wrapper.get('.sw-confirm-modal__button-confirm').text()).toBe(`global.default.${confirmText}`);
            expect(wrapper.get('.sw-confirm-modal__button-confirm').classes(`sw-button--${confirmButtonVariant}`)).toBe(
                true,
            );
        },
    );
});
