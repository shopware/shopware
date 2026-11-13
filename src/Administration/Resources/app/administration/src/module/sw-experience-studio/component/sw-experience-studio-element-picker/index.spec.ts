import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import enSnippets from '../../snippet/en.json';
import deSnippets from '../../snippet/de.json';
import pickerComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-element-picker', () => {
    const methods = (pickerComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (pickerComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

    it('uses a readable custom category when its snippet is missing', () => {
        expect(methods.categoryHeadline.call({ $te: () => false }, 'my-category', 'missing.key')).toBe('my category');
        expect(
            methods.categoryHeadline.call({ $te: () => true, $t: () => 'Eigene Kategorie' }, 'my-category', 'custom.key'),
        ).toBe('Eigene Kategorie');
    });

    it('uses the fallback locale for category headings', () => {
        const previousFallbackLocale = Shopware.Context.app.fallbackLocale;
        Shopware.Context.app.fallbackLocale = 'en-GB';
        const i18n = createI18n({
            legacy: false,
            locale: 'fr-FR',
            fallbackLocale: 'en-GB',
            fallbackWarn: false,
            messages: { 'fr-FR': {}, 'en-GB': enSnippets },
        });

        try {
            expect(
                methods.categoryHeadline.call(
                    {
                        $te: (key: string, locale?: 'en-GB' | 'fr-FR') => i18n.global.te(key, locale),
                        $t: (key: string) => i18n.global.t(key),
                    },
                    'content',
                    'sw-experience-studio.detail.elementPicker.categoryHeadlines.content',
                ),
            ).toBe(enSnippets['sw-experience-studio'].detail.elementPicker.categoryHeadlines.content);
        } finally {
            Shopware.Context.app.fallbackLocale = previousFallbackLocale;
        }
    });

    it('normalizes unknown and invalid categories to other', () => {
        const vm = {
            fallbackCategoryKey: 'other',
        };

        expect(methods.normalizeCategoryKey.call(vm, null)).toBe('other');
        expect(methods.normalizeCategoryKey.call(vm, '***')).toBe('other');
    });

    it('groups elements and keeps category order with others last', () => {
        const vm = {
            visibleElements: [
                { name: 'type-1', label: 'Image', icon: null, category: 'media' },
                { name: 'type-2', label: 'Text', icon: null, category: 'content' },
                { name: 'type-3', label: 'Grid', icon: null, category: 'layout' },
                { name: 'type-4', label: 'Listing', icon: null, category: 'commerce', priority: 1000 },
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
            'type-7',
            'type-5',
        ]);
        expect(groups[2].key).toBe('commerce');
        expect(groups[3].key).toBe('media');
        expect(groups[4].key).toBe('other');
    });

    it.each(['elements', 'presets'])(
        'orders %s by descending priority, label, and identifier without changing the input',
        (activeTab) => {
            const elements = [
                { name: 'negative', label: 'A', priority: -100 },
                { name: 'default-z', label: 'Z' },
                { name: 'same-b', label: 'Same', priority: 100 },
                { name: 'highest', label: 'Z', priority: 200 },
                { name: 'same-a', label: 'Same', priority: 100 },
                { name: 'default-a', label: 'A', priority: 0 },
                { name: 'first-label', label: 'A', priority: 100 },
            ];
            const original = [...elements];
            const vm = {
                activeTab,
                visibleElements: elements,
                categoryOrder: [],
                fallbackCategoryKey: 'other',
                normalizeCategoryKey: methods.normalizeCategoryKey,
                categoryHeadlineSnippetKey: methods.categoryHeadlineSnippetKey,
            };

            const groups = computed.groupedElements.call(vm) as Array<{ elements: Array<{ name: string }> }>;

            expect(groups[0].elements.map((element) => element.name)).toEqual([
                'highest',
                'first-label',
                'same-a',
                'same-b',
                'default-a',
                'default-z',
                'negative',
            ]);
            expect(elements).toEqual(original);
        },
    );

    it('groups presets by category before sorting by priority and puts uncategorized presets under Other', () => {
        const elements = [
            { name: 'z', label: 'Z', kind: 'preset', category: 'content', priority: -100 },
            { name: 'a', label: 'A', kind: 'preset', category: 'content', priority: 100 },
            { name: 'layout', label: 'Layout', kind: 'preset', category: 'layout', priority: -1000 },
            { name: 'legacy', label: 'Legacy', kind: 'preset' },
        ];
        const vm = {
            activeTab: 'presets',
            visibleElements: elements,
            categoryOrder: ['layout', 'content', 'commerce'],
            fallbackCategoryKey: 'other',
            normalizeCategoryKey: methods.normalizeCategoryKey,
            categoryHeadlineSnippetKey: methods.categoryHeadlineSnippetKey,
        };

        const groups = computed.groupedElements.call(vm) as Array<{ key: string; elements: typeof elements }>;

        expect(groups.map((group) => group.key)).toEqual(['layout', 'content', 'other']);
        expect(groups.map((group) => group.elements.map((item) => item.name))).toEqual([['layout'], ['a', 'z'], ['legacy']]);
    });

    it('emits select-preset for a preset item and select for an element item', () => {
        const emit = jest.fn();

        methods.onSelect.call(
            { $emit: emit },
            { name: 'core.text-block', label: 'Text block', icon: null, kind: 'preset', id: 'core.text-block' },
        );
        methods.onSelect.call({ $emit: emit }, { name: 'Sw:Content:Text', label: 'Text', icon: null, kind: 'element' });

        expect(emit).toHaveBeenNthCalledWith(1, 'select-preset', 'core.text-block');
        expect(emit).toHaveBeenNthCalledWith(2, 'select', 'Sw:Content:Text');
    });

    it('shows a description tooltip only for presets that have a description', () => {
        expect(
            methods.itemTooltip.call({}, { name: 'Sw:Content:Text', label: 'Text', icon: null, kind: 'element' }),
        ).toEqual({
            message: '',
            disabled: true,
        });

        expect(
            methods.itemTooltip.call(
                {},
                {
                    name: 'core.text-block',
                    label: 'Text block',
                    icon: null,
                    kind: 'preset',
                    id: 'core.text-block',
                    description: 'A single rich-text element.',
                },
            ),
        ).toEqual({
            message: 'A single rich-text element.',
            disabled: false,
        });

        expect(
            methods.itemTooltip.call(
                {},
                {
                    name: 'core.empty',
                    label: 'Empty',
                    icon: null,
                    kind: 'preset',
                    id: 'core.empty',
                    description: null,
                },
            ),
        ).toEqual({
            message: '',
            disabled: true,
        });
    });

    it('anchors the picker with mt-floating-ui and forwards close', async () => {
        const anchorElement = document.createElement('button');
        const wrapper = mount(pickerComponent, {
            props: {
                open: true,
                title: 'Add element',
                elements: [],
                anchorElement,
            },
            global: {
                stubs: {
                    'mt-floating-ui': {
                        name: 'mt-floating-ui',
                        props: {
                            isOpened: Boolean,
                            anchorElement: Object,
                            floatingUiOptions: Object,
                            offset: Number,
                            detached: Boolean,
                        },
                        emits: ['close'],
                        template:
                            '<div class="mt-floating-ui-stub"><slot /><button class="floating-close" @click="$emit(\'close\')" /></div>',
                    },
                    'mt-empty-state': true,
                    'sw-simple-search-field': true,
                    'mt-icon': true,
                },
            },
        });

        try {
            type FloatingUiStub = {
                props: (name: string) => unknown;
            };
            const floatingUi = wrapper.getComponent({ name: 'mt-floating-ui' }) as unknown as FloatingUiStub;

            expect(floatingUi.props('isOpened')).toBe(true);
            expect(floatingUi.props('anchorElement')).toBe(anchorElement);
            expect(floatingUi.props('detached')).toBe(true);
            expect(floatingUi.props('offset')).toBe(8);
            expect(floatingUi.props('floatingUiOptions')).toEqual({
                placement: 'right-start',
            });

            await wrapper.get('.floating-close').trigger('click');

            expect(wrapper.emitted('close')).toBeTruthy();
        } finally {
            wrapper.unmount();
        }
    });

    it('separates elements and presets into tabs and selects items from each tab', async () => {
        const wrapper = mount(pickerComponent, {
            props: {
                open: true,
                title: 'Add element',
                elements: [
                    { name: 'Sw:Content:Text', label: 'Text', icon: null, category: 'content' },
                    { name: 'Sw:Layout:Grid', label: 'Grid', icon: null, category: 'layout', kind: 'element' },
                    {
                        name: 'core.text-block',
                        label: 'Text block',
                        icon: null,
                        category: 'content',
                        priority: 100,
                        kind: 'preset',
                        id: 'core.text-block',
                    },
                    {
                        name: 'core.high-priority',
                        label: 'Z highest',
                        icon: null,
                        category: 'content',
                        priority: 200,
                        kind: 'preset',
                        id: 'core.high-priority',
                    },
                    {
                        name: 'core.tab-panel',
                        label: 'Tab panel',
                        icon: null,
                        category: 'layout',
                        priority: -100,
                        kind: 'preset',
                        id: 'core.tab-panel',
                    },
                ],
            },
            global: {
                stubs: {
                    'mt-floating-ui': {
                        name: 'mt-floating-ui',
                        props: {
                            isOpened: Boolean,
                            anchorElement: Object,
                            floatingUiOptions: Object,
                            offset: Number,
                            detached: Boolean,
                        },
                        template: '<div class="mt-floating-ui-stub"><slot /></div>',
                    },
                    'mt-empty-state': true,
                    'sw-simple-search-field': true,
                    'mt-icon': true,
                },
            },
        });

        try {
            await flushPromises();

            const tabs = wrapper.findAll('[role="tab"]');

            expect(tabs.map((tab) => tab.attributes('data-tab-name'))).toEqual(['elements', 'presets']);
            expect(tabs[0].attributes('aria-selected')).toBe('true');
            expect(wrapper.findAll('.sw-experience-studio-element-picker__label').map((label) => label.text())).toEqual([
                'Grid',
                'Text',
            ]);

            await wrapper.findAll('.sw-experience-studio-element-picker__item')[1].trigger('click');

            expect(wrapper.emitted('select')).toEqual([['Sw:Content:Text']]);
            expect(wrapper.emitted('select-preset')).toBeUndefined();

            await tabs[1].trigger('click');

            expect(tabs[1].attributes('aria-selected')).toBe('true');
            expect(wrapper.findAll('.sw-experience-studio-element-picker__label').map((label) => label.text())).toEqual([
                'Tab panel',
                'Z highest',
                'Text block',
            ]);
            expect(
                wrapper.findAll('.sw-experience-studio-element-picker__section-title').map((title) => title.text()),
            ).toEqual([
                'sw-experience-studio.detail.elementPicker.categoryHeadlines.layout',
                'sw-experience-studio.detail.elementPicker.categoryHeadlines.content',
            ]);

            await wrapper.get('.sw-experience-studio-element-picker__item').trigger('click');

            expect(wrapper.emitted('select-preset')).toEqual([['core.tab-panel']]);
            expect(wrapper.emitted('select')).toHaveLength(1);

            await tabs[0].trigger('click');

            expect(wrapper.findAll('.sw-experience-studio-element-picker__label').map((label) => label.text())).toEqual([
                'Grid',
                'Text',
            ]);
        } finally {
            wrapper.unmount();
        }
    });

    it.each([
        { elements: [], tab: 'elements', snippet: 'noElements' },
        { elements: [], tab: 'presets', snippet: 'noPresets' },
        {
            elements: [{ name: 'core.text-block', label: 'Text block', icon: null, kind: 'preset' }],
            tab: 'elements',
            snippet: 'noElements',
        },
        {
            elements: [{ name: 'Sw:Content:Text', label: 'Text', icon: null }],
            tab: 'presets',
            snippet: 'noPresets',
        },
    ])('shows $snippet for an empty $tab tab', async ({ elements, tab, snippet }) => {
        const wrapper = mount(pickerComponent, {
            props: {
                open: true,
                title: 'Add element',
                elements,
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

        try {
            await wrapper.get(`[data-tab-name="${tab}"]`).trigger('click');

            expect(wrapper.findAll('.sw-experience-studio-element-picker__item')).toHaveLength(0);
            expect(wrapper.getComponent('mt-empty-state-stub').attributes('description')).toBe(
                `sw-experience-studio.detail.elementPicker.${snippet}`,
            );
        } finally {
            wrapper.unmount();
        }
    });

    it.each([
        ['en', enSnippets],
        ['de', deSnippets],
    ])('keeps both translated tabs visible after opening and supports keyboard navigation in %s', async (_, snippets) => {
        const pickerSnippets = snippets['sw-experience-studio'].detail.elementPicker;
        const translations: Record<string, string> = {
            'sw-experience-studio.detail.elementPicker.tabElements': pickerSnippets.tabElements,
            'sw-experience-studio.detail.elementPicker.tabPresets': pickerSnippets.tabPresets,
        };
        const wrapper = mount(pickerComponent, {
            attachTo: document.body,
            props: { open: false, title: pickerSnippets.title, elements: [] },
            global: {
                mocks: { $t: (key: string) => translations[key] ?? key },
                stubs: {
                    'mt-floating-ui': {
                        props: ['isOpened'],
                        template: '<div v-if="isOpened"><slot /></div>',
                    },
                    'mt-empty-state': true,
                    'sw-simple-search-field': true,
                    'mt-icon': true,
                },
            },
        });

        try {
            expect(wrapper.findAll('[role="tab"]')).toHaveLength(0);

            await wrapper.setProps({ open: true });

            const tabs = wrapper.findAll('[role="tab"]');

            expect(tabs.map((tab) => tab.text())).toEqual([pickerSnippets.tabElements, pickerSnippets.tabPresets]);
            expect(tabs.every((tab) => tab.isVisible())).toBe(true);
            expect(tabs.map((tab) => tab.attributes('tabindex'))).toEqual(['0', '-1']);

            for (const key of [
                'ArrowRight',
                'Home',
                'End',
                'ArrowLeft',
            ]) {
                const currentTab = wrapper.get('[role="tab"][aria-selected="true"]');

                await currentTab.trigger('keydown', { key });

                const activeTab = key === 'ArrowRight' || key === 'End' ? tabs[1] : tabs[0];
                const panel = wrapper.get('[role="tabpanel"]');

                expect(activeTab.attributes('aria-selected')).toBe('true');
                expect(activeTab.attributes('tabindex')).toBe('0');
                expect(document.activeElement).toBe(activeTab.element);
                expect(activeTab.attributes('aria-controls')).toBe(panel.attributes('id'));
                expect(panel.attributes('aria-labelledby')).toBe(activeTab.attributes('id'));
            }

            window.dispatchEvent(new Event('resize'));
            await flushPromises();

            expect(wrapper.findAll('[role="tab"]')).toHaveLength(2);
            expect(wrapper.find('[aria-haspopup]').exists()).toBe(false);
        } finally {
            wrapper.unmount();
        }
    });
});
