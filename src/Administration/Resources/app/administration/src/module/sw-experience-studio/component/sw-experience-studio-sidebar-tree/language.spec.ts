import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';
import { translationProperty, translationType } from '../sw-experience-studio-translation-settings/index.spec/fixtures';
import treeComponent from './index';
import '../../store/experience-studio-element-type.store';

const english = Shopware.Defaults.systemLanguageId;
const german = 'a'.repeat(32);

async function createWrapper(languageId: string = english, title = { [english]: 'Details', [german]: 'Einzelheiten' }) {
    const type = {
        ...translationType({ title: translationProperty() }),
        name: 'Sw:Test:Tab',
        label: 'Tab',
        labelSnippet: 'element.tab',
    };
    const types = Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
    types.typesByName = { [type.name]: type };
    const elements: ContentElementNode[] = [
        {
            id: 'parent',
            component: type.name,
            properties: { title },
            slots: {
                content: [
                    {
                        id: 'child',
                        component: type.name,
                        properties: { title: { [english]: 'Overview', [german]: 'Übersicht' } },
                    },
                ],
            },
        },
    ];
    const layout = { id: 'layout', name: 'Layout', layout: elements };
    const draft = JSON.stringify(layout);
    const wrapper = mount(treeComponent as Component, {
        props: { layout, languageId },
        global: {
            provide: { acl: { can: () => false } },
            mocks: {
                $te: (key: string) => key === 'element.tab',
                $t: (key: string) => (key === 'element.tab' ? 'Registerkarte' : key),
            },
            stubs: {
                'sw-experience-studio-sidebar-tree-node': await wrapTestComponent('sw-experience-studio-sidebar-tree-node', {
                    sync: true,
                }),
            },
        },
    });
    await flushPromises();

    return { wrapper, layout, draft };
}

describe('sidebar tree content languages', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        view?.wrapper.unmount();
        (Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore).$reset();
    });

    it('updates root and nested titles while keeping one label and an Administration type tooltip per node', async () => {
        const adminLanguage = Shopware.Context.api.languageId;
        view = await createWrapper();
        const labels = () =>
            view.wrapper.findAll('.sw-experience-studio-sidebar-tree-node__name').map((node) => node.text());
        const tooltips = () =>
            view.wrapper.findAll('.sw-experience-studio-sidebar-tree-node__label').map((node) => node.attributes('title'));

        expect(labels()).toEqual(['Details', 'Overview']);
        expect(tooltips()).toEqual(['Registerkarte', 'Registerkarte']);

        await view.wrapper.setProps({ languageId: german });
        expect(labels()).toEqual(['Einzelheiten', 'Übersicht']);
        expect(tooltips()).toEqual(['Registerkarte', 'Registerkarte']);

        await view.wrapper.setProps({ languageId: english });
        expect(labels()).toEqual(['Details', 'Overview']);
        expect(JSON.stringify(view.layout)).toBe(view.draft);
        expect(view.wrapper.emitted()).toEqual({});
        expect(Shopware.Context.api.languageId).toBe(adminLanguage);
    });

    it.each([
        { [english]: 'Details' },
        { [english]: 'Details', [german]: '' },
        { [english]: 'Details', [german]: '   ' },
    ])('uses the Administration type label when the selected title is missing or blank: %j', async (title) => {
        view = await createWrapper(german, title);

        expect(view.wrapper.findAll('.sw-experience-studio-sidebar-tree-node__name').map((node) => node.text())).toEqual([
            'Registerkarte',
            'Übersicht',
        ]);
        expect(JSON.stringify(view.layout)).toBe(view.draft);
        expect(view.wrapper.emitted()).toEqual({});
    });
});
