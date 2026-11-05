import { mount } from '@vue/test-utils';
import pickerComponent from './index';

function createWrapper(icon: string | null, illustration: string | null = null, kind: 'element' | 'preset' = 'element') {
    return mount(pickerComponent, {
        props: {
            open: true,
            title: 'Add element',
            elements: [{ name: 'Sw:Media:Image', label: 'Image', icon, illustration, kind, id: 'Sw:Media:Image' }],
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
        'renders the separate illustration for an %s and keeps its selection behavior',
        async (kind) => {
            const wrapper = createWrapper('regular-star', 'media-image', kind);

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
                expect(wrapper.get('.sw-experience-studio-element-picker__item').attributes('aria-label')).toBe('Image');

                await wrapper.get('.sw-experience-studio-element-picker__item').trigger('click');

                expect(wrapper.emitted(kind === 'preset' ? 'select-preset' : 'select')).toEqual([['Sw:Media:Image']]);
            } finally {
                wrapper.unmount();
            }
        },
    );

    it.each([
        ['regular-image', 'regular-image'],
        [null, 'regular-square'],
        ['', 'regular-square'],
    ])('renders the Meteor icon %s when no illustration is selected', (icon, expectedIcon) => {
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

    it.each([
        ['regular-star', 'regular-star'],
        [null, 'regular-square'],
    ])('falls back to %s when the declared illustration cannot load', async (icon, expectedIcon) => {
        const wrapper = createWrapper(icon, 'missing-illustration');

        try {
            expect(wrapper.get('img').attributes('src')).toContain('/missing-illustration.svg');

            await wrapper.get('img').trigger('error');

            expect(wrapper.find('img').exists()).toBe(false);
            expect(wrapper.get('.sw-experience-studio-element-picker__icon-square mt-icon-stub').attributes('name')).toBe(
                expectedIcon,
            );
        } finally {
            wrapper.unmount();
        }
    });

    it('loads a different illustration after a previous one failed', async () => {
        const wrapper = createWrapper('regular-star', 'missing-illustration');

        try {
            await wrapper.get('img').trigger('error');
            await wrapper.setProps({
                elements: [{ name: 'image', label: 'Image', icon: 'regular-image', illustration: 'media-image' }],
            });

            expect(wrapper.get('img').attributes('src')).toContain('/media-image.svg');
        } finally {
            wrapper.unmount();
        }
    });
});
