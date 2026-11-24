import { shallowMount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import EntityCollection from 'src/core/data/entity-collection.data';
import { createContentLayoutRepository } from '../../util/content-layout-repository.util';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

const english = Shopware.Defaults.systemLanguageId;
const german = 'a'.repeat(32) as EntityKey<'language'>;
const french = 'b'.repeat(32) as EntityKey<'language'>;
const names = { [english]: 'English', [german]: 'German' };
const productNames = { [english]: 'Blue shirt', [german]: 'Blaues Hemd' };

async function createWrapper() {
    const layoutRepository = createContentLayoutRepository(Shopware.Service('repositoryFactory').create('content_layout'));
    const layout = Object.assign(layoutRepository.create(Shopware.Context.api), {
        name: 'Product page',
        rootSource: 'product',
        layout: [],
    });
    const get = jest.fn((id: string, context: apiContext) =>
        Promise.resolve({ id, name: productNames[context.languageId ?? ''] }),
    );
    const search = jest.fn((_criteria: unknown, context: apiContext) =>
        Promise.resolve(new EntityCollection('/product', 'product', context, null, [])),
    );
    const createRepository = jest.fn(() => ({ get, search }));
    const wrapper = shallowMount({ ...detailComponent, created() {}, mounted() {}, beforeUnmount() {} } as Component, {
        data: () => ({
            layout,
            activeFrameId: 'english-frame',
            previewSalesChannelId: 'channel',
            previewLanguageContext: {
                salesChannelId: 'channel',
                defaultLanguageId: english,
                languageIds: [english, german],
                languageNames: names,
                languageParents: { [english]: null, [german]: null },
            },
            frames: [english, german].map((languageId, index) => ({
                id: index === 0 ? 'english-frame' : 'german-frame',
                layoutId: layout.id,
                languageId,
                selectedElementId: null,
                previewEntityId: 'same-product',
                viewport: 'desktop',
            })),
            previewEntityId: 'same-product',
            openLayouts: [{ id: layout.id, layout, savedState: '', isNew: false, isSaving: false, saveError: null }],
        }),
        global: {
            provide: {
                acl: { can: () => false },
                repositoryFactory: { create: createRepository },
            },
            stubs: {
                'sw-page': { template: '<div><slot name="content" /></div>' },
                'sw-experience-studio-canvas': {
                    props: ['frames'],
                    template: `<div>
                        <section v-for="frame in frames" :key="frame.id" :data-frame-id="frame.id">
                            <slot name="frame-menu" :frame="frame" popover-class="test-popover" />
                        </section>
                    </div>`,
                },
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', { sync: true }),
                'sw-select-base': {
                    template:
                        '<div class="test-select-base"><slot name="sw-select-selection" /><slot name="results-list" /></div>',
                },
                'sw-select-result-list': { template: '<div />', methods: { setActiveItemIndex() {} } },
                'sw-product-variant-info': { template: '<span><slot /></span>' },
            },
        },
    });
    await flushPromises();

    return { wrapper, get, search, createRepository };
}

describe('frame language contexts', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;
    let originalLanguageId: apiContext['languageId'];
    let originalLanguage: apiContext['language'];

    beforeEach(() => {
        originalLanguageId = Shopware.Context.api.languageId;
        originalLanguage = Shopware.Context.api.language;
        Shopware.Store.get('context').api.languageId = german;
        Shopware.Store.get('context').api.language = { name: 'German' };
    });

    afterEach(() => {
        view?.wrapper.unmount();
        Shopware.Store.get('experienceStudioEditor').reset();
        Shopware.Store.get('context').api.languageId = originalLanguageId;
        Shopware.Store.get('context').api.language = originalLanguage;
    });

    function entitySelector(frameId: string) {
        return view.wrapper.getComponent<ComponentPublicInstance<{ context: apiContext }>>(
            `[data-frame-id="${frameId}"] .sw-entity-single-select`,
        );
    }

    function languageSelector(frameId: string) {
        return view.wrapper.getComponent<ComponentPublicInstance<{ modelValue: string }>>(
            `[data-frame-id="${frameId}"] .sw-experience-studio-frame-menu__language`,
        );
    }

    function productLabel(frameId: string) {
        return entitySelector(frameId).get('.sw-entity-single-select__selection-text').text();
    }

    it('loads the same preview entity in each frame language despite the global Admin language', async () => {
        view = await createWrapper();

        expect(productLabel('english-frame')).toBe('Blue shirt');
        expect(productLabel('german-frame')).toBe('Blaues Hemd');
        expect(view.get).toHaveBeenCalledWith(
            'same-product',
            expect.objectContaining({ languageId: english }),
            expect.anything(),
            undefined,
        );
        expect(view.get).toHaveBeenCalledWith(
            'same-product',
            expect.objectContaining({ languageId: german }),
            expect.anything(),
            undefined,
        );
        expect(entitySelector('english-frame').props('context')).toEqual(
            expect.objectContaining({ languageId: english, language: { name: 'English', parentId: undefined } }),
        );
        expect(languageSelector('english-frame').props('modelValue')).toBe(english);
        expect(view.createRepository).not.toHaveBeenCalledWith('language');
        expect(Shopware.Context.api.languageId).toBe(german);
    });

    it('reloads the selected entity label when its frame language changes without reopening the menu', async () => {
        view = await createWrapper();
        // Keep the frame active so the test only changes its language.
        languageSelector('english-frame').vm.$emit('update:modelValue', german);
        await flushPromises();

        expect(productLabel('english-frame')).toBe('Blaues Hemd');
        expect(productLabel('german-frame')).toBe('Blaues Hemd');
        const canvas = view.wrapper.getComponent<
            ComponentPublicInstance<{ frames: { languageLabel: string; ariaLabel: string }[] }>
        >({ ref: 'experienceStudioCanvas' });
        expect(canvas.props('frames').map((frame) => frame.languageLabel)).toEqual(['German', 'German']);
        expect(canvas.props('frames')[0].ariaLabel).toContain('German');
        expect(view.createRepository).not.toHaveBeenCalledWith('language');
        expect(Shopware.Context.api.languageId).toBe(german);
    });

    it('keeps both frame contexts independent when another module changes the global language', async () => {
        view = await createWrapper();
        Shopware.Store.get('context').api.languageId = french;
        Shopware.Store.get('context').api.language = { name: 'French' };
        await flushPromises();

        for (const [frameId, languageId] of [
            ['english-frame', english],
            ['german-frame', german],
        ]) {
            const selector = entitySelector(frameId);
            selector.getComponent<ComponentPublicInstance>('.test-select-base').vm.$emit('select-expanded');
            await flushPromises();
            expect(view.search).toHaveBeenLastCalledWith(
                expect.anything(),
                expect.objectContaining({ languageId }),
                undefined,
            );
        }
        expect(productLabel('english-frame')).toBe('Blue shirt');
        expect(productLabel('german-frame')).toBe('Blaues Hemd');
        expect(Shopware.Context.api.languageId).toBe(french);
    });
});
