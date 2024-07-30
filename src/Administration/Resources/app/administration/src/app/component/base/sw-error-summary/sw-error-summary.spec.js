/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper(errors = {}, options = {}) {
    if (typeof Shopware.State.get('error') !== 'undefined') {
        Shopware.State.unregisterModule('error');
    }

    Shopware.State.registerModule('error', {
        namespaced: true,

        state: {
            api: errors,
        },
    });
    Shopware.State.getters['error/getAllApiErrors'] = () => [errors];

    return mount(await wrapTestComponent('sw-error-summary', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'sw-alert': await wrapTestComponent('sw-alert'),
                'sw-alert-deprecated': await wrapTestComponent('sw-alert-deprecated'),
                'sw-icon': true,
                'mt-banner': true,
            },
            ...options,
        },
    });
}

describe('src/app/component/base/sw-error-summary/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show alert box without errors', () => {
        const alert = wrapper.find('.sw-alert');

        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert box with errors', async () => {
        wrapper = await createWrapper(
            {
                entity: {
                    someId: {
                        someProperty: {
                            _code: 'error-1',
                            _detail: 'Error 1',
                            selfLink: 'error-1',
                        },
                        otherProperty: {
                            _code: 'error-1',
                            _detail: 'Error 1',
                            selfLink: 'error-2',
                        },
                        somethingStrange: null,
                    },
                },
            },
            {
                mocks: {
                    $te: () => false,
                },
            },
        );
        await flushPromises();

        const alert = wrapper.find('.sw-alert');
        expect(alert.exists()).toBeTruthy();

        const quantity = wrapper.find('.sw-error-summary__quantity');
        expect(quantity.exists()).toBeTruthy();
        expect(quantity.text()).toBe('2x');

        const message = wrapper.find('.sw-alert__message');
        expect(message.exists()).toBeTruthy();
        expect(message.text()).toBe('2x "Error 1"');
    });
});
