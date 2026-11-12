import { flushPromises, mount } from '@vue/test-utils';
import pickerComponent from './index';

const elements = [
    { name: 'Sw:Grid:Container', label: 'Grid container', icon: null, category: 'layout' },
    { name: 'Sw:Content:Text', label: 'Text', icon: null, category: 'content', priority: 100 },
    { name: 'Sw:Media:Image', label: 'Image', icon: null, category: 'content' },
    { name: 'Sw:Product:Reviews', label: 'Product reviews', icon: null, category: 'commerce', priority: 100 },
    { name: 'Sw:Product:ReviewSummary', label: 'Product review summary', icon: null, category: 'commerce', priority: 200 },
    {
        name: 'Sw:ProductReviews',
        id: 'Sw:ProductReviews',
        label: 'Product review section',
        icon: null,
        category: 'commerce',
        kind: 'preset',
    },
    {
        name: 'Sw:MediaAndText',
        id: 'Sw:MediaAndText',
        label: 'Media & text',
        icon: null,
        category: 'content',
        kind: 'preset',
    },
];

async function createWrapper() {
    const wrapper = mount(pickerComponent, {
        props: { open: true, title: 'Add element', elements },
        global: {
            stubs: {
                'mt-floating-ui': { template: '<div><slot /></div>' },
                'mt-empty-state': true,
                'mt-icon': true,
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
            },
        },
    });

    await flushPromises();

    return wrapper;
}

function visibleLabels(wrapper: Awaited<ReturnType<typeof createWrapper>>): string[] {
    return wrapper.findAll('.sw-experience-studio-element-picker__label').map((label) => label.text());
}

describe('Experience Studio picker search', () => {
    it.each([
        { term: '  rEvIeW  ', expected: ['Product review summary', 'Product reviews'] },
        { term: 'SW:MEDIA:IMAGE', expected: ['Image'] },
    ])('matches labels and identifiers case-insensitively for "$term"', async ({ term, expected }) => {
        const wrapper = await createWrapper();

        try {
            const input = wrapper.get('input');
            expect(input.attributes('aria-label')).toBe(
                'sw-experience-studio.detail.elementPicker.searchElementsPlaceholder',
            );
            await input.setValue(term);
            expect(visibleLabels(wrapper)).toEqual(expected);
        } finally {
            wrapper.unmount();
        }
    });

    it('updates the placeholder and accessible label when switching tabs', async () => {
        const wrapper = await createWrapper();

        try {
            for (const tab of ['elements', 'presets', 'elements']) {
                await wrapper.get(`[data-tab-name="${tab}"]`).trigger('click');

                const expected =
                    tab === 'presets'
                        ? 'sw-experience-studio.detail.elementPicker.searchPresetsPlaceholder'
                        : 'sw-experience-studio.detail.elementPicker.searchElementsPlaceholder';
                const input = wrapper.get('input');

                expect(input.attributes('placeholder')).toBe(expected);
                expect(input.attributes('aria-label')).toBe(expected);
            }
        } finally {
            wrapper.unmount();
        }
    });

    it('keeps priority ordering and hides empty categories while searching', async () => {
        const wrapper = await createWrapper();

        try {
            await wrapper.get('input').setValue('review');
            expect(visibleLabels(wrapper)).toEqual(['Product review summary', 'Product reviews']);
            expect(
                wrapper.findAll('.sw-experience-studio-element-picker__section-title').map((title) => title.text()),
            ).toEqual([
                'sw-experience-studio.detail.elementPicker.categoryHeadlines.commerce',
            ]);
            await wrapper.get('.sw-experience-studio-element-picker__item').trigger('click');
            expect(wrapper.emitted('select')).toEqual([['Sw:Product:ReviewSummary']]);
        } finally {
            wrapper.unmount();
        }
    });

    it('keeps the query when switching tabs and filters presets independently', async () => {
        const wrapper = await createWrapper();

        try {
            await wrapper.get('input').setValue('review');
            await wrapper.get('[data-tab-name="presets"]').trigger('click');
            expect(visibleLabels(wrapper)).toEqual(['Product review section']);
            expect(wrapper.get('input').element.value).toBe('review');
            await wrapper.get('.sw-experience-studio-element-picker__item').trigger('click');
            expect(wrapper.emitted('select-preset')).toEqual([['Sw:ProductReviews']]);
            await wrapper.get('[data-tab-name="elements"]').trigger('click');
            expect(visibleLabels(wrapper)).toEqual(['Product review summary', 'Product reviews']);
        } finally {
            wrapper.unmount();
        }
    });

    it('shows the search empty state and restores all items when the search is cleared', async () => {
        const wrapper = await createWrapper();

        try {
            await wrapper.get('input').setValue('unmatched term');
            expect(visibleLabels(wrapper)).toEqual([]);
            expect(wrapper.getComponent('mt-empty-state-stub').attributes('description')).toBe(
                'sw-experience-studio.detail.elementPicker.noSearchResults',
            );
            await wrapper.get('[data-tab-name="presets"]').trigger('click');
            expect(visibleLabels(wrapper)).toEqual([]);
            await wrapper.get('input').setValue('');
            expect(visibleLabels(wrapper)).toEqual(['Media & text', 'Product review section']);
            await wrapper.get('[data-tab-name="elements"]').trigger('click');
            await wrapper.get('input').setValue('   ');
            expect(visibleLabels(wrapper)).toEqual([
                'Grid container',
                'Text',
                'Image',
                'Product review summary',
                'Product reviews',
            ]);
        } finally {
            wrapper.unmount();
        }
    });

    it('clears the previous query when the picker is reopened', async () => {
        const wrapper = await createWrapper();

        try {
            await wrapper.get('input').setValue('review');
            await wrapper.setProps({ open: false });
            await wrapper.setProps({ open: true });
            expect(wrapper.get('input').element.value).toBe('');
            expect(visibleLabels(wrapper)).toHaveLength(5);
        } finally {
            wrapper.unmount();
        }
    });

    it('filters newly supplied choices without restoring elements unavailable at this position', async () => {
        const wrapper = await createWrapper();

        try {
            await wrapper.get('input').setValue('review');
            await wrapper.setProps({ elements: [elements[2]] });
            expect(visibleLabels(wrapper)).toEqual([]);
            await wrapper.get('input').setValue('');
            expect(visibleLabels(wrapper)).toEqual(['Image']);
        } finally {
            wrapper.unmount();
        }
    });
});
