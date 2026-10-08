import { DOMWrapper, mount } from '@vue/test-utils';
import type { Component } from 'vue';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

async function createWrapper(failedLayoutId: string | null = null, newLayoutId: string | null = null) {
    const layouts = [
        {
            id: 'about',
            name: 'About us',
            rootSource: 'category',
            layout: [{ id: 'a', component: 'Sw:Content:Text', properties: { text: 'Saved' } }],
        },
        {
            id: 'faq',
            name: 'FAQ',
            rootSource: 'category',
            layout: [{ id: 'b', component: 'Sw:Content:Text', properties: { text: 'Saved' } }],
        },
    ];
    const openLayouts = layouts.map((layout) => ({
        id: layout.id,
        layout,
        selectedElementId: null,
        viewport: 'desktop',
        previewEntityId: null,
        settingsWidth: 320,
        savedState: JSON.stringify({ name: layout.name, rootSource: layout.rootSource, layout: layout.layout }),
        isNew: layout.id === newLayoutId,
        isSaving: false,
        saveError: null,
    }));
    layouts.forEach((layout) => {
        layout.layout[0].properties.text = 'Edited';
    });
    const save = jest.fn((layout: { id: string }) =>
        layout.id === failedLayoutId ? Promise.reject(new Error('Save failed')) : Promise.resolve(),
    );
    const get = jest.fn((id: string) =>
        Promise.resolve(Shopware.Utils.object.cloneDeep(layouts.find((layout) => layout.id === id) ?? null)),
    );
    const toolbar = await wrapTestComponent('sw-experience-studio-toolbar', { sync: true });
    const wrapper = mount(
        {
            ...detailComponent,
            created() {},
            mounted() {},
            beforeUnmount() {},
            methods: {
                ...detailComponent.methods,
                diagnoseLayout: jest.fn(),
                createNotificationSuccess: jest.fn(),
                createNotificationError: jest.fn(),
            },
        } as Component,
        {
            attachTo: document.body,
            global: {
                provide: {
                    repositoryFactory: { create: () => ({ save, get }) },
                    acl: { can: () => true },
                },
                mocks: { $route: { name: 'sw.experience.studio.detail', params: { id: 'about' } } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-toolbar': toolbar,
                    'sw-experience-studio-workspace-switcher': true,
                    'sw-experience-studio-canvas': { template: '<div />', methods: { closeSettingsPanel: jest.fn() } },
                    'sw-experience-studio-create-wizard': true,
                    'sw-experience-studio-element-picker': true,
                    'sw-experience-studio-workspace-picker': true,
                    'sw-experience-studio-layout-picker': true,
                    'sw-experience-studio-assignment-modal': true,
                    'sw-entity-single-select': true,
                    'sw-modal': true,
                },
            },
        },
    );
    await wrapper.setData({ layout: layouts[0], activeLayoutId: 'about', openLayouts });
    await flushPromises();
    return { wrapper, save, get };
}

function getPanel() {
    const element = document.body.querySelector<HTMLElement>('.sw-experience-studio-toolbar__review-panel');
    if (!element) {
        throw new Error('Review panel is not open');
    }
    return new DOMWrapper(element);
}

describe('layout change review integration', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        view?.wrapper.unmount();
    });

    async function openReview() {
        await view.wrapper.get('.sw-experience-studio-toolbar__review-trigger').trigger('click');
        await flushPromises();
    }

    it('updates the counter on discard and saves only the remaining changed layout', async () => {
        view = await createWrapper();
        expect(view.wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('2');
        await openReview();
        await getPanel().findAll('li button')[0].trigger('click');
        await flushPromises();

        expect(view.wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('1');
        expect(getPanel().findAll('li')).toHaveLength(1);
        expect(getPanel().get('.sw-experience-studio-toolbar__changed-layout-name').text()).toBe('FAQ');
        expect(view.save).not.toHaveBeenCalled();

        await getPanel().get('.sw-experience-studio-toolbar__review-footer button').trigger('click');
        await flushPromises();

        expect(view.save.mock.calls.map(([layout]) => layout.id)).toEqual(['faq']);
        expect(view.wrapper.find('.sw-experience-studio-toolbar__change-count').exists()).toBe(false);
        expect(view.wrapper.get('.sw-experience-studio-toolbar__review-trigger').attributes('aria-expanded')).toBe('false');
    });

    it('discards a new unsaved layout without saving it or changing another layout', async () => {
        view = await createWrapper(null, 'about');
        await openReview();
        await getPanel().findAll('li button')[0].trigger('click');
        await flushPromises();

        expect(view.wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('1');
        expect(getPanel().findAll('li')).toHaveLength(1);
        expect(getPanel().get('.sw-experience-studio-toolbar__changed-layout-name').text()).toBe('FAQ');
        expect(view.save).not.toHaveBeenCalled();
        expect(view.get).not.toHaveBeenCalled();
    });

    it('keeps failed layouts in the review while successful saves leave the list', async () => {
        view = await createWrapper('faq');
        await openReview();
        await getPanel().get('.sw-experience-studio-toolbar__review-footer button').trigger('click');
        await flushPromises();

        expect(view.save.mock.calls.map(([layout]) => layout.id)).toEqual(['about', 'faq']);
        expect(view.wrapper.get('.sw-experience-studio-toolbar__change-count').text()).toBe('1');
        expect(getPanel().findAll('li')).toHaveLength(1);
        expect(getPanel().get('.sw-experience-studio-toolbar__changed-layout-name').text()).toBe('FAQ');
        expect(getPanel().get('.sw-experience-studio-toolbar__review-footer button').attributes('disabled')).toBeUndefined();
    });
});
