/**
 * @sw-package framework
 */

import { mount, flushPromises } from '@vue/test-utils';

const createWrapper = async (attrs: Record<string, string> = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-select-field-deprecated', { sync: true }), {
        attrs,
        global: {
            stubs: {
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-loader': true,
            },
            provide: {
                validationService: {},
            },
        },
        props: {
            options: [{ id: '1', name: 'One' }],
            value: '1',
        },
    });

    // sw-block-field / sw-base-field resolve asynchronously.
    await flushPromises();

    return wrapper;
};

describe('src/app/component/form/sw-select-field-deprecated', () => {
    it('forwards aria-label to the native select element', async () => {
        const wrapper = await createWrapper({ 'aria-label': 'Pick a value' });

        expect(wrapper.get('select').attributes('aria-label')).toBe('Pick a value');
    });

    it('forwards aria-labelledby to the native select element', async () => {
        const wrapper = await createWrapper({ 'aria-labelledby': 'external-label-id' });

        expect(wrapper.get('select').attributes('aria-labelledby')).toBe('external-label-id');
    });
});
