import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';

describe('Experience Studio preview navigation', () => {
    it('lists only the layout previews and selects or closes them by frame identity', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-preview-list', { sync: true }), {
            props: {
                layoutId: 'product-page',
                frames: [
                    {
                        id: 'desktop',
                        layout: { id: 'product-page' },
                        label: 'Product page',
                        languageLabel: 'English',
                        viewport: 'desktop',
                        isActive: false,
                    },
                    {
                        id: 'mobile',
                        layout: { id: 'product-page' },
                        label: 'Product page',
                        languageLabel: 'German',
                        ariaLabel: 'Product page · Mobile · German',
                        viewport: 'mobile',
                        isActive: true,
                    },
                    { id: 'other', layout: { id: 'category-page' }, label: 'Category preview', isActive: false },
                ],
            },
        });

        try {
            const previews = wrapper.findAll('li');
            expect(previews).toHaveLength(2);
            expect(wrapper.text()).not.toContain('Category preview');
            expect(previews.map((preview) => preview.get('.sw-experience-studio-preview-list__name').text())).toEqual([
                'English',
                'German',
            ]);
            expect(previews[1].get('[aria-current="true"]').text()).toBe('German');
            expect(previews[0].get('.sw-experience-studio-preview-list__select').attributes('aria-label')).toBe('English');
            expect(previews[1].get('.sw-experience-studio-preview-list__select').attributes('title')).toBe(
                'Product page · Mobile · German',
            );
            expect(previews[0].find('[data-testid="mt-icon__regular-desktop"]').exists()).toBe(true);
            expect(previews[1].find('[data-testid="mt-icon__regular-mobile"]').exists()).toBe(true);
            await previews[0].get('.sw-experience-studio-preview-list__select').trigger('click');
            await previews[1].get('.sw-experience-studio-preview-list__close').trigger('click');

            expect(wrapper.emitted('select')).toEqual([['desktop']]);
            expect(wrapper.emitted('close')).toEqual([['mobile']]);
        } finally {
            wrapper.unmount();
        }
    });

    it('updates the frame language label when it becomes available or changes', async () => {
        const frame = {
            id: 'preview',
            layout: { id: 'product-page' },
            label: 'Product page',
            viewport: 'desktop',
            isActive: true,
        };
        const wrapper = shallowMount(
            (await wrapTestComponent('sw-experience-studio-preview-list', { sync: true })) as Component,
            { props: { layoutId: 'product-page', frames: [frame] } },
        );

        try {
            expect(wrapper.get('.sw-experience-studio-preview-list__name').text()).toBe('Product page');
            await wrapper.setProps({ frames: [{ ...frame, languageLabel: 'English' }] });
            expect(wrapper.get('.sw-experience-studio-preview-list__name').text()).toBe('English');
            await wrapper.setProps({ frames: [{ ...frame, languageLabel: 'German' }] });
            expect(wrapper.get('.sw-experience-studio-preview-list__name').text()).toBe('German');
        } finally {
            wrapper.unmount();
        }
    });
});
