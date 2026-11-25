import { DOMWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';
import { createWrapper, english, german } from './translation-panel.spec/fixtures';

describe('frame layout translation panel', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        view?.wrapper.unmount();
        Shopware.Store.get('experienceStudioEditor').reset();
        (Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore).$reset();
        jest.restoreAllMocks();
    });

    async function activateFrameToolbar(frameId: string) {
        const frame = view.wrapper.get(`[data-frame-id="${frameId}"]`);
        await frame.get('.sw-experience-studio-canvas__move-handle').trigger('click');
        await flushPromises();
        return frame.get('.sw-experience-studio-canvas__frame-toolbar');
    }

    async function openTranslations(frameId = 'german-frame') {
        const menu = await activateFrameToolbar(frameId);
        await menu.get('.sw-experience-studio-frame-menu__translate').trigger('click');
        await flushPromises();
    }

    function languageSwitch(selector = '.sw-experience-studio-detail__translation-language') {
        return view.wrapper.getComponent<
            ComponentPublicInstance<{
                modelValue: string;
                options: Array<{ value: string; label: string }>;
                disabled: boolean;
            }>
        >(selector);
    }

    async function selectLanguage(label: string, selector = languageSwitch()) {
        await selector.get('.mt-select__selection').trigger('click');
        await flushPromises();
        const option = new DOMWrapper(document.body).findAll('.mt-select-result').find((result) => result.text() === label);
        expect(option).toBeDefined();
        await option!.trigger('pointerdown', { button: 0 });
        await option!.trigger('click');
        await flushPromises();
    }

    it('opens a separate element list for an inactive frame, including fields in nested elements', async () => {
        view = await createWrapper();
        await openTranslations();

        expect(view.wrapper.get('[data-frame-id="german-frame"]').classes()).toContain('is--active');
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
        expect(view.wrapper.get('.sw-experience-studio-canvas__panel-header h2').text()).toBe(
            'sw-experience-studio.detail.translationSettings.title',
        );
        expect(view.wrapper.find('sw-experience-studio-element-settings-stub').exists()).toBe(false);
        expect(view.wrapper.find('sw-experience-studio-layout-tree-stub').exists()).toBe(true);
        expect(view.wrapper.findAll('.sw-experience-studio-translation-settings__element')).toHaveLength(3);
        expect(
            view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]').map((input) => input.element.value),
        ).toEqual(['Erster', 'Verschachtelt']);
        expect(view.wrapper.find('input[aria-label="Shared"]').exists()).toBe(false);
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '640px',
        );
    });

    it('updates the selected language in the shared draft, supports undo/redo and saves through the existing layout action', async () => {
        view = await createWrapper();
        const adminLanguage = Shopware.Context.api.languageId;
        await openTranslations();
        await view.wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowRight' });
        await view.wrapper.findAll('input[aria-label="Title"]')[0].setValue('Neuer Titel');

        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '620px',
        );
        expect(view.layout.layout[0].properties?.title).toEqual({ [english]: 'First', [german]: 'Neuer Titel' });
        expect(view.layout.layout[0].properties?.shared).toBe('Keep shared');
        expect(view.wrapper.findAll('.test-preview-title').map((preview) => preview.text())).toEqual([
            'First',
            'Neuer Titel',
        ]);
        expect(view.save).not.toHaveBeenCalled();
        await view.wrapper
            .get('[data-frame-id="german-frame"] button[aria-label="sw-experience-studio.detail.toolbar.undo"]')
            .trigger('click');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('Erster');
        await view.wrapper
            .get('[data-frame-id="german-frame"] button[aria-label="sw-experience-studio.detail.toolbar.redo"]')
            .trigger('click');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('Neuer Titel');

        view.wrapper.getComponent<ComponentPublicInstance>('sw-experience-studio-toolbar-stub').vm.$emit('save');
        await flushPromises();
        expect(view.save).toHaveBeenCalledTimes(1);
        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(true);
        expect(Shopware.Context.api.languageId).toBe(adminLanguage);
    });

    it('preserves missing and explicitly empty translations when blank fields are opened and the layout is saved', async () => {
        view = await createWrapper();
        view.layout.layout[0].properties = { title: { [english]: 'First' }, shared: 'Keep shared' };
        view.layout.layout[1].slots!.content[0].properties = { title: { [english]: 'Nested', [german]: '' } };
        const original = Shopware.Utils.object.cloneDeep(view.layout.layout);

        await openTranslations();

        expect(
            view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]').map((input) => input.element.value),
        ).toEqual(['', '']);
        expect(view.layout.layout).toEqual(original);
        expect(view.editor.canUndo).toBe(false);

        // An unrelated change saves the draft without reconstructing translations from the blank inputs.
        view.layout.name = 'Renamed layout';
        view.wrapper.getComponent<ComponentPublicInstance>('sw-experience-studio-toolbar-stub').vm.$emit('save');
        await flushPromises();

        expect(view.save).toHaveBeenCalledTimes(1);
        expect(view.save).toHaveBeenCalledWith(expect.objectContaining({ layout: original }), Shopware.Context.api);
        expect(view.layout.layout).toEqual(original);
    });

    it('closes on frame changes and follows language changes after explicitly reopening translations', async () => {
        view = await createWrapper();
        await openTranslations();
        expect(languageSwitch().props('modelValue')).toBe(german);
        expect(languageSwitch('.sw-experience-studio-frame-menu__language').props('options')).toEqual([
            { value: english, label: 'English' },
            { value: german, label: 'German' },
        ]);
        expect(languageSwitch().props('options')).toEqual(
            languageSwitch('.sw-experience-studio-frame-menu__language').props('options'),
        );
        await view.wrapper.get('[data-frame-id="english-frame"]').trigger('click');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        await openTranslations('english-frame');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('First');
        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(true);
        expect(languageSwitch().props('modelValue')).toBe(english);

        await activateFrameToolbar('english-frame');
        await selectLanguage('German', languageSwitch('.sw-experience-studio-frame-menu__language'));
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('Erster');
        expect(languageSwitch().props('modelValue')).toBe(german);
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame-toolbar')).toHaveLength(1);
    });

    it('switches the frame and fields from a toolbar outside the scrolling list without changing the draft', async () => {
        view = await createWrapper();
        const adminLanguage = Shopware.Context.api.languageId;
        const draft = Shopware.Utils.object.cloneDeep(view.layout.layout);
        await openTranslations();
        const panel = view.wrapper.get('.sw-experience-studio-canvas__panel--settings');
        expect(panel.find('.sw-experience-studio-detail__translation-toolbar').exists()).toBe(true);
        expect(
            panel
                .get('.sw-experience-studio-canvas__panel-content')
                .find('.sw-experience-studio-detail__translation-toolbar')
                .exists(),
        ).toBe(false);
        await view.wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowRight' });

        await selectLanguage('English');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('First');
        expect(view.wrapper.findAll('.test-preview-title').map((preview) => preview.text())).toEqual(['First', 'First']);
        await activateFrameToolbar('german-frame');
        expect(languageSwitch('.sw-experience-studio-frame-menu__language').props('modelValue')).toBe(english);

        await selectLanguage('German');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('Erster');
        expect(view.wrapper.findAll('.test-preview-title').map((preview) => preview.text())).toEqual(['First', 'Erster']);
        expect((panel.element as HTMLElement).style.width).toBe('620px');
        expect(view.layout.layout).toEqual(draft);
        expect(view.editor.canUndo).toBe(false);
        expect(view.save).not.toHaveBeenCalled();
        expect(Shopware.Context.api.languageId).toBe(adminLanguage);
    });

    it('only offers available sales-channel languages and blocks changes while the context is loading', async () => {
        view = await createWrapper();
        await view.wrapper.setData({ previewLanguageContext: { languageIds: [english] } });
        await openTranslations('english-frame');
        const selectors = [
            '.sw-experience-studio-detail__translation-language',
            '.sw-experience-studio-frame-menu__language',
        ];
        for (const selector of selectors) {
            expect(languageSwitch(selector).props('options')).toEqual([{ value: english, label: 'English' }]);
            languageSwitch(selector).vm.$emit('update:modelValue', german);
            await flushPromises();
            expect(languageSwitch(selector).props('modelValue')).toBe(english);
        }

        await view.wrapper.setData({ previewLanguageContext: { salesChannelId: 'previous-channel' } });
        for (const selector of selectors) {
            expect(languageSwitch(selector).props('disabled')).toBe(true);
            expect(languageSwitch(selector).props('options')).toEqual([]);
            languageSwitch(selector).vm.$emit('update:modelValue', german);
            await flushPromises();
            expect(languageSwitch(selector).props('modelValue')).toBe(english);
        }
        expect(view.editor.canUndo).toBe(false);
    });

    it('renders no preview channel and keeps settings read-only while a new channel context loads', async () => {
        view = await createWrapper();
        await openTranslations('english-frame');
        const previews = () => view.wrapper.findAll('.test-preview');
        const titleInputs = () => view.wrapper.findAll('input[aria-label="Title"]');
        expect(previews().every((preview) => preview.attributes('data-sales-channel-id') === 'channel')).toBe(true);
        expect(titleInputs().some((input) => input.attributes('disabled') === undefined)).toBe(true);

        await view.wrapper.setData({ previewLanguageContext: { salesChannelId: 'previous-channel' } });

        expect(previews().every((preview) => preview.attributes('data-sales-channel-id') === undefined)).toBe(true);
        expect(titleInputs().every((input) => input.attributes('disabled') !== undefined)).toBe(true);
        await view.wrapper.setData({ settingsPanelMode: 'element' });
        expect(view.wrapper.get('sw-experience-studio-element-settings-stub').attributes('allow-edit')).toBe('false');

        await view.wrapper.setData({ previewLanguageContext: { salesChannelId: 'channel' } });
        expect(view.wrapper.get('sw-experience-studio-element-settings-stub').attributes('allow-edit')).toBe('true');
    });

    it('applies rich-text sizing when type metadata finishes loading after the panel opens', async () => {
        view = await createWrapper();
        const types = Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
        const registeredTypes = types.typesByName;
        types.typesByName = {};
        types.isLoading = true;
        await openTranslations();
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '320px',
        );

        types.typesByName = registeredTypes;
        types.isLoading = false;
        await flushPromises();
        expect(view.wrapper.findAll('.sw-experience-studio-translation-settings__element')).toHaveLength(3);
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '640px',
        );
    });

    it('scrolls only the active preview to the focused field without changing the panel or selection', async () => {
        view = await createWrapper();
        await openTranslations();
        await view.wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowRight' });
        const englishPreview = view.wrapper.get('[data-frame-id="english-frame"] .test-preview');
        const germanPreview = view.wrapper.get('[data-frame-id="german-frame"] .test-preview');
        const fields = view.wrapper.findAll('input[aria-label="Title"]');
        await fields[0].trigger('focusin');
        expect(germanPreview.attributes('data-scroll-target')).toBe('first-text');
        await fields[1].trigger('focusin');
        expect(germanPreview.attributes('data-scroll-target')).toBe('nested-text');
        await view.wrapper.get('textarea').trigger('focusin');
        expect(germanPreview.attributes('data-scroll-target')).toBe('rich-text');
        expect(englishPreview.attributes('data-scroll-target')).toBeUndefined();
        expect(view.wrapper.find('sw-experience-studio-element-settings-stub').exists()).toBe(false);
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '620px',
        );

        await view.wrapper.get('[data-frame-id="english-frame"]').trigger('click');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        await openTranslations('english-frame');
        await view.wrapper.findAll('input[aria-label="Title"]')[0].trigger('focusin');
        expect(englishPreview.attributes('data-scroll-target')).toBe('first-text');
        expect(germanPreview.attributes('data-scroll-target')).toBe('rich-text');
        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(true);
        expect(view.editor.canUndo).toBe(false);
        expect(view.save).not.toHaveBeenCalled();
    });

    it('returns to normal settings when an element is selected', async () => {
        view = await createWrapper();
        await openTranslations();
        await view.wrapper.get('[data-frame-id="german-frame"] .test-select-element').trigger('click');

        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(false);
        expect(view.wrapper.find('.sw-experience-studio-detail__translation-toolbar').exists()).toBe(false);
        expect(view.wrapper.find('sw-experience-studio-element-settings-stub').exists()).toBe(true);
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '320px',
        );
    });

    it('keeps layout translations and their resized width when an element is deselected', async () => {
        view = await createWrapper();
        await view.wrapper.get('[data-frame-id="english-frame"] .test-select-element').trigger('click');
        await openTranslations('english-frame');
        await view.wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowRight' });
        await view.wrapper.get('[data-frame-id="english-frame"] .test-clear-selection').trigger('click');

        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(true);
        expect((view.wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '620px',
        );
    });

    it.each([
        { panel: 'element', source: 'german-frame' },
        { panel: 'element', source: 'english-frame' },
        { panel: 'translations', source: 'german-frame' },
        { panel: 'translations', source: 'english-frame' },
    ])('closes $panel settings when opening another preview from $source', async ({ panel, source }) => {
        view = await createWrapper();
        await view.wrapper.get('[data-frame-id="german-frame"] .test-select-element').trigger('click');
        if (panel === 'translations') {
            await openTranslations();
        }
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
        const menu = await activateFrameToolbar(source);
        await menu.get('.sw-experience-studio-frame-menu__open-another').trigger('click');
        await flushPromises();

        const frames = view.wrapper.findAll('.sw-experience-studio-canvas__frame');
        expect(frames).toHaveLength(3);
        expect(frames[2].classes()).toContain('is--active');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        expect(view.wrapper.find('.sw-experience-studio-detail__translation-toolbar').exists()).toBe(false);

        await frames[2].get('.test-select-element').trigger('click');
        expect(view.wrapper.find('sw-experience-studio-element-settings-stub').exists()).toBe(true);
        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(false);
    });

    it('closes the translation panel when the active preview closes', async () => {
        view = await createWrapper();
        await openTranslations();
        await view.wrapper.get('[data-frame-id="german-frame"] .sw-experience-studio-canvas__close-frame').trigger('click');

        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame.is--active')).toHaveLength(0);
    });

    it('allows viewers to inspect translations without changing the layout', async () => {
        view = await createWrapper('viewer');
        await openTranslations();
        await selectLanguage('English');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('First');
        expect(
            view.wrapper.findAll('input[aria-label="Title"]').every((input) => input.attributes('disabled') !== undefined),
        ).toBe(true);
        view.wrapper
            .getComponent<ComponentPublicInstance>('.sw-experience-studio-settings-fields')
            .vm.$emit('update-field', { key: 'title', value: 'Blocked' });
        expect(view.layout.layout[0].properties?.title).toEqual({ [english]: 'First', [german]: 'Erster' });
        expect(view.editor.canUndo).toBe(false);
    });
});
