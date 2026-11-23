import { DOMWrapper, mount } from '@vue/test-utils';
import { defineComponent, type Component, type ComponentPublicInstance } from 'vue';
import Entity from 'src/core/data/entity.data';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';
import { createContentLayoutRepository } from '../../util/content-layout-repository.util';
import {
    translationProperty,
    translationType,
} from '../../component/sw-experience-studio-translation-settings/index.spec/fixtures';
import translationSettings from '../../component/sw-experience-studio-translation-settings';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

const english = Shopware.Defaults.systemLanguageId;
const german = 'a'.repeat(32);

async function createWrapper(role: 'editor' | 'viewer' = 'editor') {
    const textType = translationType({
        title: translationProperty(),
        shared: translationProperty({ title: 'Shared', translatable: false }),
    });
    const richType = {
        ...translationType({ text: translationProperty({ title: 'Text', adminUI: { component: 'text-editor' } }) }),
        name: 'Sw:RichTranslationTest',
    };
    const types = Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
    types.typesByName = { [textType.name]: textType, [richType.name]: richType };
    const repository = createContentLayoutRepository(Shopware.Service('repositoryFactory').create('content_layout'));
    const layout = Object.assign(repository.create(Shopware.Context.api), {
        name: 'Product page',
        rootSource: 'product',
        layout: [
            {
                id: 'first-text',
                component: textType.name,
                properties: { title: { [english]: 'First', [german]: 'Erster' }, shared: 'Keep shared' },
            },
            {
                id: 'container',
                component: 'Plugin:Container',
                slots: {
                    content: [
                        {
                            id: 'nested-text',
                            component: textType.name,
                            properties: { title: { [english]: 'Nested', [german]: 'Verschachtelt' } },
                        },
                        {
                            id: 'rich-text',
                            component: richType.name,
                            properties: { text: { [english]: '<p>Text</p>', [german]: '<p>Text DE</p>' } },
                        },
                    ],
                },
            },
        ],
    });
    const save = jest.fn().mockResolvedValue(undefined);
    const get = jest.fn(() =>
        Promise.resolve(new Entity(layout.id, 'content_layout', Shopware.Utils.object.cloneDeep(layout.getDraft()))),
    );
    const editor = Shopware.Store.get('experienceStudioEditor');
    editor.initialize(layout.id);
    const wrapper = mount(
        {
            ...detailComponent,
            created() {},
            mounted() {},
            beforeUnmount() {},
            methods: {
                ...detailComponent.methods,
                diagnoseLayout: jest.fn(),
                scheduleDiagnoseLayout: jest.fn(),
                createNotificationSuccess: jest.fn(),
            },
        } as Component,
        {
            attachTo: document.body,
            data: () => ({
                layout,
                activeFrameId: 'english-frame',
                previewSalesChannelId: 'channel',
                previewLanguageContext: {
                    salesChannelId: 'channel',
                    defaultLanguageId: english,
                    languageIds: [english, german],
                    languageNames: { [english]: 'English', [german]: 'German' },
                    languageParents: {},
                },
                frames: [english, german].map((languageId, index) => ({
                    id: index === 0 ? 'english-frame' : 'german-frame',
                    layoutId: layout.id,
                    languageId,
                    selectedElementId: null,
                    viewport: 'desktop',
                    previewEntityId: 'product',
                })),
                previewEntityId: 'product',
                openLayouts: [
                    {
                        id: layout.id,
                        layout,
                        savedState: JSON.stringify({
                            name: layout.name,
                            rootSource: layout.rootSource,
                            layout: layout.layout,
                        }),
                        isNew: false,
                        isSaving: false,
                        saveError: null,
                    },
                ],
            }),
            global: {
                provide: {
                    acl: {
                        can: (privilege: string) =>
                            privilege === 'experience_studio.viewer' ||
                            (role === 'editor' && privilege === 'experience_studio.editor'),
                    },
                    repositoryFactory: { create: () => ({ save, get }) },
                },
                mocks: { $route: { params: {} } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-canvas': await wrapTestComponent('sw-experience-studio-canvas', { sync: true }),
                    'sw-experience-studio-frame-menu': await wrapTestComponent('sw-experience-studio-frame-menu', {
                        sync: true,
                    }),
                    'sw-experience-studio-translation-settings': translationSettings,
                    'sw-experience-studio-settings-fields': await wrapTestComponent('sw-experience-studio-settings-fields', {
                        sync: true,
                    }),
                    'sw-experience-studio-toolbar': true,
                    'sw-experience-studio-layout-tree': true,
                    'sw-experience-studio-sidebar-tree': true,
                    'sw-experience-studio-element-settings': true,
                    'sw-experience-studio-element-picker': true,
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'sw-entity-single-select': {
                        props: ['entity', 'value'],
                        template: '<div class="test-entity-select" :data-entity="entity" />',
                    },
                    'sw-experience-studio-preview': defineComponent({
                        props: ['layout', 'languageId'],
                        data: () => ({ scrollTarget: null as string | null }),
                        methods: {
                            scrollToElement(elementId: string) {
                                this.scrollTarget = elementId;
                            },
                        },
                        template: `<div class="test-preview" :data-scroll-target="scrollTarget">
                        <span class="test-preview-title">{{ layout.layout[0].properties.title[languageId] }}</span>
                        <button class="test-select-element" @click="$emit('select-element', 'first-text')">Select element</button>
                        <button class="test-clear-selection" @click="$emit('select-element', null)">Clear selection</button>
                    </div>`,
                    }),
                    'mt-text-editor': {
                        props: ['label', 'modelValue', 'disabled'],
                        template: `<div><textarea :aria-label="label" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)" /></div>`,
                    },
                },
            },
        },
    );
    jest.spyOn(wrapper.get('.sw-experience-studio-detail__workspace').element, 'getBoundingClientRect').mockReturnValue({
        width: 1600,
    } as DOMRect);
    jest.spyOn(wrapper.get('.sw-experience-studio-canvas__stage').element, 'getBoundingClientRect').mockReturnValue({
        left: 0,
        top: 0,
        width: 1200,
        height: 800,
    } as DOMRect);
    await wrapper.getComponent<ComponentPublicInstance>({ ref: 'experienceStudioCanvas' }).setData({ canvasZoom: 0.25 });
    await flushPromises();
    return { wrapper, layout, save, editor };
}

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

    function languageSwitch() {
        return view.wrapper.getComponent<
            ComponentPublicInstance<{
                modelValue: string;
                options: Array<{ value: string; label: string }>;
                disabled: boolean;
            }>
        >('.sw-experience-studio-detail__translation-language');
    }

    async function selectLanguage(label: string) {
        await languageSwitch().get('.mt-select__selection').trigger('click');
        await flushPromises();
        const option = new DOMWrapper(document.body).findAll('.mt-select-result').find((result) => result.text() === label);
        expect(option).toBeDefined();
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

    it('closes on frame changes and follows language changes after explicitly reopening translations', async () => {
        view = await createWrapper();
        await openTranslations();
        expect(languageSwitch().props('modelValue')).toBe(german);
        await view.wrapper.get('[data-frame-id="english-frame"]').trigger('click');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        await openTranslations('english-frame');
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('First');
        expect(view.wrapper.find('.sw-experience-studio-translation-settings').exists()).toBe(true);
        expect(languageSwitch().props('modelValue')).toBe(english);

        await activateFrameToolbar('english-frame');
        view.wrapper
            .getComponent<ComponentPublicInstance>('.test-entity-select[data-entity="language"]')
            .vm.$emit('update:value', german, { parentId: null });
        await flushPromises();
        expect(view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]')[0].element.value).toBe('Erster');
        expect(languageSwitch().props('modelValue')).toBe(german);
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
        expect(
            view.wrapper
                .getComponent<ComponentPublicInstance<{ value: string }>>('.test-entity-select[data-entity="language"]')
                .props('value'),
        ).toBe(english);

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
        expect(languageSwitch().props('options')).toEqual([{ value: english, label: 'English' }]);
        languageSwitch().vm.$emit('update:modelValue', german);
        await flushPromises();
        expect(languageSwitch().props('modelValue')).toBe(english);

        await view.wrapper.setData({ previewLanguageContext: { salesChannelId: 'previous-channel' } });
        expect(languageSwitch().props('disabled')).toBe(true);
        expect(languageSwitch().props('options')).toEqual([]);
        languageSwitch().vm.$emit('update:modelValue', german);
        await flushPromises();
        expect(languageSwitch().props('modelValue')).toBe(english);
        expect(view.editor.canUndo).toBe(false);
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
