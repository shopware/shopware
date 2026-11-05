import { mount } from '@vue/test-utils';
import pickerComponent from './index';

function createWrapper(icon: string | null, kind: 'element' | 'preset' = 'element') {
    return mount(pickerComponent, {
        props: {
            open: true,
            title: 'Add element',
            elements: [{ name: 'Sw:Media:Image', label: 'Image', icon, kind, id: 'Sw:Media:Image' }],
        },
        global: {
            stubs: {
                'mt-floating-ui': { template: '<div><slot /></div>' },
                'mt-empty-state': true,
                'sw-simple-search-field': true,
                'mt-icon': true,
            },
        },
    });
}

describe('Experience Studio picker illustrations', () => {
    it.each(['element', 'preset'] as const)(
        'renders a decorative local SVG for an %s and keeps its selection behavior',
        async (kind) => {
            const wrapper = createWrapper('local:media-image', kind);

            try {
                if (kind === 'preset') {
                    await wrapper.get('[data-tab-name="presets"]').trigger('click');
                }

                const illustration = wrapper.get('img');
                expect(illustration.attributes('src')).toContain(
                    '/static/img/experience-studio/element-icons/media-image.svg',
                );
                expect(illustration.attributes('alt')).toBe('');
                expect(illustration.attributes('aria-hidden')).toBe('true');
                expect(wrapper.get('.sw-experience-studio-element-picker__item').text()).toBe('Image');

                await wrapper.get('.sw-experience-studio-element-picker__item').trigger('click');

                expect(wrapper.emitted(kind === 'preset' ? 'select-preset' : 'select')).toEqual([['Sw:Media:Image']]);
            } finally {
                wrapper.unmount();
            }
        },
    );

    it.each([
        ['regular-image', 'regular-image'],
        ['local:missing', 'regular-square'],
        [null, 'regular-square'],
    ])('renders the Meteor fallback for %s', (icon, expectedIcon) => {
        const wrapper = createWrapper(icon);

        try {
            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.get('.sw-experience-studio-element-picker__icon-square mt-icon-stub').attributes('name')).toBe(
                expectedIcon,
            );
        } finally {
            wrapper.unmount();
        }
    });

    it('falls back to the compact Meteor icon when an illustration cannot load', async () => {
        const wrapper = createWrapper('local:media-image');

        try {
            await wrapper.get('img').trigger('error');

            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.get('.sw-experience-studio-element-picker__icon-square mt-icon-stub').attributes('name')).toBe(
                'regular-image',
            );
        } finally {
            wrapper.unmount();
        }
    });
});
