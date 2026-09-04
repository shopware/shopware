import pickerComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-element-picker', () => {
    const methods = (pickerComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (pickerComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

    it('normalizes unknown and invalid categories to other', () => {
        const vm = {
            fallbackCategoryKey: 'other',
        };

        expect(methods.normalizeCategoryKey.call(vm, null)).toBe('other');
        expect(methods.normalizeCategoryKey.call(vm, '***')).toBe('other');
    });

    it('groups elements and keeps category order with others last', () => {
        const vm = {
            elements: [
                { name: 'type-1', label: 'Image', icon: null, category: 'media' },
                { name: 'type-2', label: 'Text', icon: null, category: 'content' },
                { name: 'type-3', label: 'Grid', icon: null, category: 'layout' },
                { name: 'type-4', label: 'Listing', icon: null, category: 'commerce' },
                { name: 'type-5', label: 'Text 2', icon: null, category: 'Content' },
                { name: 'type-7', label: 'Text', icon: null, category: 'content' },
                { name: 'type-6', label: 'Unknown', icon: null, category: null },
            ],
            categoryOrder: [
                'layout',
                'content',
                'commerce',
            ],
            fallbackCategoryKey: 'other',
            normalizeCategoryKey: methods.normalizeCategoryKey,
            categoryHeadlineSnippetKey: methods.categoryHeadlineSnippetKey,
        };

        const groups = computed.groupedElements.call(vm) as Array<{
            key: string;
            headlineSnippetKey: string;
            elements: Array<{ name: string }>;
        }>;

        expect(groups).toHaveLength(5);
        expect(groups[0].key).toBe('layout');
        expect(groups[1].key).toBe('content');
        expect(groups[1].headlineSnippetKey).toBe('sw-experience-studio.detail.elementPicker.categoryHeadlines.content');
        expect(groups[1].elements.map((element) => element.name)).toEqual([
            'type-2',
            'type-5',
            'type-7',
        ]);
        expect(groups[2].key).toBe('commerce');
        expect(groups[3].key).toBe('media');
        expect(groups[4].key).toBe('other');
    });
});
