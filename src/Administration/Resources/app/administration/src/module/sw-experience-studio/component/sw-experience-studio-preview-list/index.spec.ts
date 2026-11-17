import { shallowMount } from '@vue/test-utils';

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
                        viewport: 'desktop',
                        isActive: false,
                    },
                    {
                        id: 'mobile',
                        layout: { id: 'product-page' },
                        label: 'Product page',
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
            expect(previews[1].get('[aria-current="true"]').text()).toBe('Product page');
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
});
