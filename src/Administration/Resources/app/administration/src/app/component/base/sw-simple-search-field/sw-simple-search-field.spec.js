/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-simple-search-field', { sync: true }), {
        props: {
            value: 'search term',
        },
        global: {
            stubs: {
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': true,
                'icons-small-search': true,
                'sw-field-copyable': await wrapTestComponent('sw-field-copyable'),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                'sw-ai-copilot-badge': await wrapTestComponent('sw-ai-copilot-badge'),
                'sw-help-text': await wrapTestComponent('sw-help-text'),
            },
            provide: {
                validationService: {},
            },
        },
    });
}

describe('components/base/sw-simple-search-field', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have `search term` as initial value', async () => {
        expect(wrapper.find('input[type="text"]').element.value).toBe('search term');
    });

    it('should emit `input` event', async () => {
        await wrapper.find('input[type="text"]').setValue('@input Sw Simple Search Field Typing');

        /* wait for `$emit('input')` */
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted().input).toBeTruthy();
    });
});
