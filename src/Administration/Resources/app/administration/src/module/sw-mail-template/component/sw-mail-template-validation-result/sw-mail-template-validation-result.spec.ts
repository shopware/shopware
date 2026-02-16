/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props: object = {}) {
    return mount(await wrapTestComponent('sw-mail-template-validation-result', { sync: true }), {
        props: {
            ...props,
        },
    });
}

describe('modules/sw-mail-template/component/sw-mail-template-validation-result', () => {
    it('should show red warning icon when no type given', async () => {
        const wrapper = await createWrapper();

        const icon = wrapper.findComponent('.sw-mail-template-validation-result__icon');

        expect(icon.props('name')).toBe('solid-exclamation-circle');
        expect(icon.props('color')).toBe('var(--color-icon-critical-default)');
    });

    it('should show red warning icon when type error given', async () => {
        const wrapper = await createWrapper({ type: 'error' });

        const icon = wrapper.findComponent('.sw-mail-template-validation-result__icon');

        expect(icon.props('name')).toBe('solid-exclamation-circle');
        expect(icon.props('color')).toBe('var(--color-icon-critical-default)');
    });

    it('should show red warning icon when type warning given', async () => {
        const wrapper = await createWrapper({ type: 'warning' });

        const icon = wrapper.findComponent('.sw-mail-template-validation-result__icon');

        expect(icon.props('name')).toBe('solid-exclamation-triangle');
        expect(icon.props('color')).toBe('var(--color-icon-attention-default)');
    });

    it('should show given title', async () => {
        const wrapper = await createWrapper({ title: 'foo' });

        const title = wrapper.find('.mt-banner__title');

        expect(title.text()).toBe('foo');
    });

    it('should show given hint', async () => {
        const wrapper = await createWrapper({ hint: 'bar' });

        const hint = wrapper.find('.sw-mail-template-validation-result__hint');

        expect(hint.text()).toBe('bar');
    });
});
