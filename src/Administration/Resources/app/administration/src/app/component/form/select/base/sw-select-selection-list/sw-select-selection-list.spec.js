/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/base/sw-label';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-select-selection-list', { sync: true }), {
        global: {
            stubs: {
                'sw-label': {
                    template: '<div class="sw-label"><slot></slot></div>',
                },
            },
        },
        propsData: {
            ...propsData,
        },
    });
}

describe('src/app/component/form/select/base/sw-select-selection-list', () => {
    it('should render dismissable labels', async () => {
        const wrapper = await createWrapper({
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.sw-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBe('true');
    });

    it('should pass autocomplete attribute to input', async () => {
        const wrapper = await createWrapper({
            autocomplete: 'off',
        });

        const input = wrapper.find('.sw-select-selection-list__input');
        expect(input.attributes('autocomplete')).toBe('off');
    });

    it('should not render autocomplete attribute by default', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('.sw-select-selection-list__input');
        expect(input.attributes('autocomplete')).toBeUndefined();
    });

    it('should render labels which are not dismissable', async () => {
        const wrapper = await createWrapper({
            disabled: true,
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.sw-label');
        expect(element.exists()).toBeTruthy();
        if (element.attributes().hasOwnProperty('dismissable')) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(element.attributes().dismissable).toBe('false');
        } else {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(element.attributes().dismissable).toBeFalsy();
        }
    });
});
