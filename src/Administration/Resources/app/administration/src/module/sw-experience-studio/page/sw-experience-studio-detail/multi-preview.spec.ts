import { mount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import Entity from 'src/core/data/entity.data';
import type Criteria from 'src/core/data/criteria.data';
import detailComponent from './index';
import { createContentLayoutRepository } from '../../util/content-layout-repository.util';
import '../../store/experience-studio-workspace.store';

async function createWrapper(role: 'viewer' | 'editor' = 'editor') {
    const repository = createContentLayoutRepository(Shopware.Service('repositoryFactory').create('content_layout'));
    const layout = Object.assign(repository.create(Shopware.Context.api), {
        name: 'Product page',
        rootSource: 'product',
        layout: [{ id: 'text', component: 'Sw:Content:Text', properties: { text: 'Original copy' } }],
    });
    const save = jest.fn().mockResolvedValue(undefined);
    const get = jest.fn(() =>
        Promise.resolve(new Entity(layout.id, 'content_layout', Shopware.Utils.object.cloneDeep(layout.getDraft()))),
    );
    const canvas = await wrapTestComponent('sw-experience-studio-canvas', { sync: true });
    const frameMenu = await wrapTestComponent('sw-experience-studio-frame-menu', { sync: true });
    const layoutTree = await wrapTestComponent('sw-experience-studio-layout-tree', { sync: true });
    const previewList = await wrapTestComponent('sw-experience-studio-preview-list', { sync: true });
    Shopware.Store.get('experienceStudioEditor').initialize(layout.id);
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
                createNotificationError: jest.fn(),
            },
        } as Component,
        {
            attachTo: document.body,
            data: () => ({
                layout,
                activeFrameId: 'first-preview',
                previewSalesChannelId: 'preview-channel',
                previewLanguageContext: {
                    salesChannelId: 'preview-channel',
                    defaultLanguageId: Shopware.Defaults.systemLanguageId,
                    languageIds: [Shopware.Defaults.systemLanguageId, 'a'.repeat(32)],
                    languageNames: { [Shopware.Defaults.systemLanguageId]: 'English', ['a'.repeat(32)]: 'German' },
                    languageParents: {},
                },
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
                frames: [
                    {
                        id: 'first-preview',
                        layoutId: layout.id,
                        viewport: 'desktop',
                        previewEntityId: 'product-a',
                        selectedElementId: null,
                    },
                ],
                previewEntityId: 'product-a',
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
                    'sw-experience-studio-canvas': canvas,
                    'sw-experience-studio-frame-menu': frameMenu,
                    'sw-experience-studio-layout-tree': layoutTree,
                    'sw-experience-studio-preview-list': previewList,
                    'sw-provide': { inheritAttrs: false, template: '<slot />' },
                    'sw-experience-studio-toolbar': true,
                    'sw-experience-studio-preview': {
                        name: 'test-preview',
                        props: ['layout', 'entityId', 'languageId'],
                        template:
                            '<button class="test-preview" @click="$emit(\'select-element\', \'text\')">{{ layout.layout[0]?.properties.text }}</button>',
                    },
                    'sw-entity-single-select': {
                        name: 'test-entity-select',
                        props: ['entity', 'value', 'criteria'],
                        template: '<div class="test-entity-select" :data-entity="entity" />',
                    },
                    'sw-experience-studio-element-settings': true,
                    'sw-experience-studio-element-picker': true,
                    'sw-experience-studio-layout-picker': true,
                    'sw-experience-studio-workspace-picker': true,
                    'sw-modal': { template: '<div class="test-dialog"><slot /><slot name="modal-footer" /></div>' },
                },
            },
        },
    );
    const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
    jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ left: 0, top: 0, width: 2000, height: 1200 } as DOMRect);
    await flushPromises();
    return { wrapper, layout, save, get };
}

describe('multiple previews of one layout', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        view?.wrapper.unmount();
        Shopware.Store.get('experienceStudioEditor').reset();
        jest.restoreAllMocks();
    });

    async function activateFrameToolbar(frameId = 'first-preview') {
        const frame = view.wrapper.get(`[data-frame-id="${frameId}"]`);
        await frame.get('.sw-experience-studio-canvas__move-handle').trigger('click');
        await flushPromises();
        return frame.get('.sw-experience-studio-canvas__frame-toolbar');
    }

    async function openAnotherPreview() {
        const menu = await activateFrameToolbar();
        await menu.get('.sw-experience-studio-frame-menu__open-another').trigger('click');
        await flushPromises();
        return view.wrapper.findAll('.sw-experience-studio-canvas__frame')[1];
    }

    it.each(['editor', 'viewer'] as const)(
        'keeps frame languages independent for an %s without changing the Admin language or draft',
        async (role) => {
            view = await createWrapper(role);
            const systemLanguage = Shopware.Defaults.systemLanguageId;
            const germanLanguage = 'a'.repeat(32);
            const parentLanguage = 'b'.repeat(32);
            const adminLanguage = Shopware.Context.api.languageId;
            await activateFrameToolbar();
            const languageSelector = () =>
                view.wrapper.getComponent<ComponentPublicInstance<{ value: string; criteria: Criteria }>>(
                    '.test-entity-select[data-entity="language"]',
                );
            expect(languageSelector().props('value')).toBe(systemLanguage);
            languageSelector().props('criteria').setTerm('German');
            languageSelector().props('criteria').setPage(2);

            languageSelector().vm.$emit('update:value', germanLanguage, { parentId: parentLanguage });
            await flushPromises();
            await view.wrapper.get('[data-frame-id="first-preview"] .test-preview').trigger('click');
            expect(view.wrapper.getComponent('sw-experience-studio-element-settings-stub').attributes('language-id')).toBe(
                germanLanguage,
            );
            expect(view.wrapper.get('sw-experience-studio-element-settings-stub').attributes('language-name')).toBe(
                'German',
            );

            const second = await openAnotherPreview();
            expect(
                view.wrapper
                    .findAllComponents({ name: 'test-preview' })
                    .map((preview) => preview.props('languageId') as string),
            ).toEqual([germanLanguage, germanLanguage]);
            await activateFrameToolbar(second.attributes('data-frame-id'));
            expect(languageSelector().props('criteria').term).not.toBe('German');
            expect(languageSelector().props('criteria').page).toBe(1);
            languageSelector().vm.$emit('update:value', systemLanguage, { parentId: null });
            await flushPromises();
            await second.get('.test-preview').trigger('click');

            expect(
                view.wrapper
                    .findAllComponents({ name: 'test-preview' })
                    .map((preview) => preview.props('languageId') as string),
            ).toEqual([germanLanguage, systemLanguage]);
            expect(view.wrapper.getComponent('sw-experience-studio-element-settings-stub').attributes('language-id')).toBe(
                systemLanguage,
            );
            expect(view.wrapper.get('sw-experience-studio-element-settings-stub').attributes('language-name')).toBe(
                'English',
            );
            expect(Shopware.Context.api.languageId).toBe(adminLanguage);
            expect(view.wrapper.get('sw-experience-studio-toolbar-stub').attributes('changed-layouts')).toBe('');
            expect(view.save).not.toHaveBeenCalled();
        },
    );

    it('opens another preview from the floating toolbar and copies custom dimensions without copying the layout', async () => {
        view = await createWrapper();
        const canvas = view.wrapper.getComponent({ ref: 'experienceStudioCanvas' });
        await canvas.setData({ frameWidth: 640, frameHeight: 700 });
        await view.wrapper.setData({ currentViewport: 'custom' });
        const second = await openAnotherPreview();

        expect(second.attributes('data-frame-id')).not.toBe('first-preview');
        expect(second.classes()).toContain('is--active');
        expect(second.attributes('style')).toContain('width: 640px');
        expect(second.attributes('style')).toContain('height: 700px');
        expect(second.attributes('style')).not.toBe(view.wrapper.get('[data-frame-id="first-preview"]').attributes('style'));
        const previews = view.wrapper.findAllComponents({ name: 'test-preview' });
        expect(previews).toHaveLength(2);
        expect(previews[0].props('layout')).toBe(previews[1].props('layout'));
        expect(view.wrapper.findAll('.sw-experience-studio-layout-tree__select')).toHaveLength(1);
        expect(view.wrapper.findAll('.sw-experience-studio-preview-list__select')).toHaveLength(2);
        expect(view.get).not.toHaveBeenCalled();
        expect(view.save).not.toHaveBeenCalled();
    });

    it.each(['active', 'unselected'])(
        'opens another preview from the selection modal for an already open %s layout',
        async (state) => {
            view = await createWrapper();
            const first = view.wrapper.get('[data-frame-id="first-preview"]');
            await first.get('button[aria-label="sw-experience-studio.detail.toolbar.viewportMobile"]').trigger('click');
            if (state === 'unselected') {
                const second = await openAnotherPreview();
                await second.get('.sw-experience-studio-canvas__close-frame').trigger('click');
            }
            view.layout.layout[0].properties.text = 'Unsaved copy';
            await view.wrapper.get('button[aria-label="global.default.open"]').trigger('click');

            view.wrapper
                .getComponent<ComponentPublicInstance>('sw-experience-studio-layout-picker-stub')
                .vm.$emit('select', view.layout.id);
            await flushPromises();

            expect(view.wrapper.find('sw-experience-studio-layout-picker-stub').exists()).toBe(false);
            const frames = view.wrapper.findAll('.sw-experience-studio-canvas__frame');
            expect(frames).toHaveLength(2);
            expect(frames[0].attributes('data-frame-id')).not.toBe(frames[1].attributes('data-frame-id'));
            expect(frames[1].classes()).toContain('is--active');
            expect(frames[1].attributes('style')).toContain('width: 375px');
            expect(view.wrapper.findAll('.sw-experience-studio-layout-tree__select')).toHaveLength(1);
            const previews = view.wrapper.findAllComponents({ name: 'test-preview' });
            expect(previews[0].props('layout')).toBe(previews[1].props('layout'));
            expect(previews.map((preview) => preview.text())).toEqual(['Unsaved copy', 'Unsaved copy']);
            expect(view.get).not.toHaveBeenCalled();
            expect(view.save).not.toHaveBeenCalled();
        },
    );

    it('changes viewport and entity independently and navigates between frames of the same layout', async () => {
        view = await createWrapper();
        const second = await openAnotherPreview();
        await second.get('button[aria-label="sw-experience-studio.detail.toolbar.viewportMobile"]').trigger('click');
        await activateFrameToolbar(second.attributes('data-frame-id'));
        view.wrapper
            .getComponent<ComponentPublicInstance>('.test-entity-select[data-entity="language"]')
            .vm.$emit('update:value', 'a'.repeat(32), { parentId: null });
        await flushPromises();
        view.wrapper
            .getComponent<ComponentPublicInstance>('.test-entity-select[data-entity="product"]')
            .vm.$emit('update:value', 'product-b');
        await flushPromises();

        expect(second.attributes('style')).toContain('width: 375px');
        const first = view.wrapper.get('[data-frame-id="first-preview"]');
        expect(first.get('.sw-experience-studio-canvas__frame-name').text()).toBe('Product page');
        expect(second.get('.sw-experience-studio-canvas__frame-name').text()).toBe('Product page');
        expect(
            second.find('.sw-experience-studio-canvas__move-handle [data-testid="mt-icon__regular-mobile"]').exists(),
        ).toBe(true);
        expect(view.wrapper.findAll('.sw-experience-studio-preview-list__name').map((label) => label.text())).toEqual([
            'English',
            'German',
        ]);
        expect(view.wrapper.get('.sw-experience-studio-layout-tree__name').text()).toBe('Product page');
        expect(first.attributes('style')).toContain('width: 1480px');
        expect(
            view.wrapper
                .getComponent<ComponentPublicInstance<{ value: string }>>('.test-entity-select[data-entity="product"]')
                .props('value'),
        ).toBe('product-b');
        await activateFrameToolbar();
        expect(
            view.wrapper
                .getComponent<ComponentPublicInstance<{ value: string }>>('.test-entity-select[data-entity="product"]')
                .props('value'),
        ).toBe('product-a');
        await view.wrapper.findAll('.sw-experience-studio-preview-list__select')[0].trigger('click');
        expect(first.classes()).toContain('is--active');
        expect(second.classes()).not.toContain('is--active');
        expect(view.wrapper.get('sw-experience-studio-toolbar-stub').attributes('changed-layouts')).toBe('');
    });

    it('uses the same Meteor icon button appearance for all frame actions', async () => {
        view = await createWrapper();
        const frame = view.wrapper.get('[data-frame-id="first-preview"]');
        const buttons = frame.findAll(
            '.sw-experience-studio-canvas__frame-history button, .sw-experience-studio-frame-menu button, .sw-experience-studio-canvas__close-frame',
        );

        expect(buttons).toHaveLength(5);
        for (const button of buttons) {
            expect(button.classes()).toEqual(
                expect.arrayContaining([
                    'mt-button',
                    'mt-button--tertiary',
                    'mt-button--x-small',
                    'mt-button--square',
                ]),
            );
        }
        expect(frame.findAll('.test-entity-select').map((select) => select.attributes('data-entity'))).toEqual([
            'product',
            'language',
        ]);
    });

    it('shares edits and undo history across previews and saves the layout once', async () => {
        view = await createWrapper();
        const second = await openAnotherPreview();
        await second.get('.test-preview').trigger('click');
        view.wrapper
            .getComponent<ComponentPublicInstance>('sw-experience-studio-element-settings-stub')
            .vm.$emit('update-properties', {
                elementId: 'text',
                propertyKey: 'text',
                value: 'Updated copy',
            });
        await flushPromises();
        expect(view.wrapper.findAll('.test-preview').map((preview) => preview.text())).toEqual([
            'Updated copy',
            'Updated copy',
        ]);
        const first = view.wrapper.get('[data-frame-id="first-preview"]');
        await first.get('.sw-experience-studio-canvas__move-handle').trigger('click');
        await first.get('button[aria-label="sw-experience-studio.detail.toolbar.undo"]').trigger('click');
        expect(view.wrapper.findAll('.test-preview').map((preview) => preview.text())).toEqual([
            'Original copy',
            'Original copy',
        ]);
        await second.get('.sw-experience-studio-canvas__move-handle').trigger('click');
        await second.get('button[aria-label="sw-experience-studio.detail.toolbar.redo"]').trigger('click');
        view.wrapper.getComponent<ComponentPublicInstance>('sw-experience-studio-toolbar-stub').vm.$emit('save');
        await flushPromises();

        expect(view.save).toHaveBeenCalledTimes(1);
        expect(view.get).toHaveBeenCalledTimes(1);
        expect(view.wrapper.findAll('.test-preview').map((preview) => preview.text())).toEqual([
            'Updated copy',
            'Updated copy',
        ]);
        expect(view.wrapper.get('sw-experience-studio-toolbar-stub').attributes('changed-layouts')).toBe('');
        const previews = view.wrapper.findAllComponents({ name: 'test-preview' });
        expect(previews[0].props('layout')).toBe(previews[1].props('layout'));
    });

    it('retains a dirty draft when one preview closes and prompts only when its last preview closes', async () => {
        view = await createWrapper();
        const second = await openAnotherPreview();
        view.layout.layout[0].properties.text = 'Unsaved copy';
        await second.get('.sw-experience-studio-canvas__close-frame').trigger('click');
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame')).toHaveLength(1);
        expect(view.wrapper.find('.test-dialog').exists()).toBe(false);
        expect(view.wrapper.get('.test-preview').text()).toBe('Unsaved copy');
        await view.wrapper.get('.sw-experience-studio-canvas__close-frame').trigger('click');
        expect(view.wrapper.find('.test-dialog').exists()).toBe(true);
        await view.wrapper.get('.test-dialog').findAll('button')[0].trigger('click');
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame')).toHaveLength(1);
        await view.wrapper.get('.sw-experience-studio-canvas__close-frame').trigger('click');
        await view.wrapper.get('.test-dialog').findAll('button')[1].trigger('click');
        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame')).toHaveLength(0);
        expect(view.wrapper.findAll('.sw-experience-studio-layout-tree__select')).toHaveLength(0);
        expect(view.save).not.toHaveBeenCalled();
    });

    it('clears the active selection and returns to the layout overview when its preview closes', async () => {
        view = await createWrapper();
        const first = view.wrapper.get('[data-frame-id="first-preview"]');
        await first.get('.test-preview').trigger('click');
        const second = await openAnotherPreview();
        await second.get('.test-preview').trigger('click');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);

        await second.get('.sw-experience-studio-canvas__close-frame').trigger('click');

        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame')).toHaveLength(1);
        expect(first.classes()).not.toContain('is--active');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        expect(view.wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(true);
        expect(view.wrapper.find('[aria-current="page"]').exists()).toBe(false);
        expect(Shopware.Store.get('experienceStudioEditor').activeLayoutId).toBeNull();

        await first.trigger('click');
        expect(first.classes()).toContain('is--active');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
        await first.get('.test-preview').trigger('click');

        expect(first.classes()).toContain('is--active');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
    });

    it('keeps the active preview and its settings open when an inactive preview closes', async () => {
        view = await createWrapper();
        const second = await openAnotherPreview();
        await second.get('.test-preview').trigger('click');

        await view.wrapper.get('[data-frame-id="first-preview"] .sw-experience-studio-canvas__close-frame').trigger('click');

        expect(second.classes()).toContain('is--active');
        expect(view.wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
        expect(view.wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(false);
        expect(Shopware.Store.get('experienceStudioEditor').activeLayoutId).toBe(view.layout.id);
    });

    it('closes all previews when their clean layout is closed from the sidebar', async () => {
        view = await createWrapper();
        await openAnotherPreview();

        await view.wrapper.get('.sw-experience-studio-layout-tree__close').trigger('click');

        expect(view.wrapper.findAll('.sw-experience-studio-canvas__frame')).toHaveLength(0);
        expect(view.wrapper.find('.test-dialog').exists()).toBe(false);
        expect(view.save).not.toHaveBeenCalled();
    });

    it('keeps one shared dirty document after saving fails', async () => {
        view = await createWrapper();
        await openAnotherPreview();
        view.layout.layout[0].properties.text = 'Unsaved copy';
        view.save.mockRejectedValueOnce(new Error('Save failed'));

        view.wrapper.getComponent<ComponentPublicInstance>('sw-experience-studio-toolbar-stub').vm.$emit('save');
        await flushPromises();

        expect(view.save).toHaveBeenCalledTimes(1);
        expect(view.wrapper.findAll('.test-preview').map((preview) => preview.text())).toEqual([
            'Unsaved copy',
            'Unsaved copy',
        ]);
        expect(view.wrapper.findAll('.sw-experience-studio-layout-tree__select')).toHaveLength(1);
        expect(view.wrapper.findAll('.sw-experience-studio-layout-tree__dirty-indicator')).toHaveLength(1);
    });

    it('allows viewers to open and configure another preview without layout creation or editing rights', async () => {
        view = await createWrapper('viewer');
        const second = await openAnotherPreview();
        await second.get('button[aria-label="sw-experience-studio.detail.toolbar.viewportMobile"]').trigger('click');

        expect(second.attributes('style')).toContain('width: 375px');
        expect(
            second.get('button[aria-label="sw-experience-studio.detail.toolbar.undo"]').attributes('disabled'),
        ).toBeDefined();
        expect(view.save).not.toHaveBeenCalled();
    });
});
