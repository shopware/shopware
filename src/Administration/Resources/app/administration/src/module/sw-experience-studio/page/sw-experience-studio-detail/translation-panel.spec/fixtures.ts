import { mount } from '@vue/test-utils';
import { defineComponent, type Component, type ComponentPublicInstance } from 'vue';
import Entity from 'src/core/data/entity.data';
import type { ExperienceStudioElementTypeStore } from '../../../store/experience-studio-element-type.store';
import { createContentLayoutRepository } from '../../../util/content-layout-repository.util';
import {
    translationProperty,
    translationType,
} from '../../../component/sw-experience-studio-translation-settings/index.spec/fixtures';
import translationSettings from '../../../component/sw-experience-studio-translation-settings';
import detailComponent from '../index';
import '../../../store/experience-studio-workspace.store';

/** @private @sw-package discovery */
export const english = Shopware.Defaults.systemLanguageId;
/** @private @sw-package discovery */
export const german = 'a'.repeat(32);

/** @private @sw-package discovery */
export async function createWrapper(role: 'editor' | 'viewer' = 'editor') {
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
                        props: ['layout', 'languageId', 'salesChannelId'],
                        data: () => ({ scrollTarget: null as string | null }),
                        methods: {
                            scrollToElement(elementId: string) {
                                this.scrollTarget = elementId;
                            },
                        },
                        template: `<div class="test-preview" :data-scroll-target="scrollTarget" :data-sales-channel-id="salesChannelId">
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
