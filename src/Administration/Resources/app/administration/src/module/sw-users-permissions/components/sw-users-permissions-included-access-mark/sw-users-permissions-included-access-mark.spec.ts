/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

const className = 'sw-users-permissions-included-access-mark';

async function createWrapper(variant: 'full' | 'partial' | 'muted') {
    return mount(await wrapTestComponent('sw-users-permissions-included-access-mark', { sync: true }), {
        props: {
            variant,
            tooltip: 'Products › View<br>Orders › View',
            label: 'All data',
        },
        global: {
            stubs: {
                'mt-tooltip': {
                    props: ['content'],
                    template: '<span class="mt-tooltip" :data-content="content"><slot /></span>',
                },
                'mt-icon': {
                    props: ['name'],
                    template: '<i class="mt-icon" :data-name="name" />',
                },
            },
        },
    });
}

describe('src/module/sw-users-permissions/components/sw-users-permissions-included-access-mark', () => {
    it.each([
        ['full', 'regular-check-circle'],
        ['muted', 'regular-check-circle'],
        ['partial', 'regular-minus-circle'],
    ] as const)('should show the %s variant with the %s icon', async (variant, icon) => {
        const wrapper = await createWrapper(variant);

        expect(wrapper.find('.mt-icon').attributes('data-name')).toBe(icon);
        expect(wrapper.find(`.${className}`).classes()).toContain(`${className}--${variant}`);
    });

    it('should pass the tooltip and name the mark for screen readers', async () => {
        const wrapper = await createWrapper('full');

        expect(wrapper.find('.mt-tooltip').attributes('data-content')).toBe('Products › View<br>Orders › View');
        expect(wrapper.find(`.${className}__label`).text()).toBe('All data');
    });

    it('should only let keyboard users reach the partial mark', async () => {
        expect((await createWrapper('partial')).find(`.${className}`).attributes('tabindex')).toBe('0');
        expect((await createWrapper('full')).find(`.${className}`).attributes('tabindex')).toBeUndefined();
        expect((await createWrapper('muted')).find(`.${className}`).attributes('tabindex')).toBeUndefined();
    });
});
