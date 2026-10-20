/**
 * @sw-package discovery
 */
import { computed, ref } from 'vue';
import objectUtils from 'src/core/service/utils/object.utils';
import useCmsElement from './use-cms-element';
import type { CmsSlotConfig, RuntimeSlot } from 'src/module/sw-cms/service/cms.service';

const inheritedSlotConfig = ref<{ [slotId: string]: CmsSlotConfig } | null>(null);
const currentDemoEntity = ref<unknown>(null);

jest.mock('./use-cms-state', () => ({
    __esModule: true,
    default: () => ({
        cmsPageState: computed(() => ({ currentDemoEntity: currentDemoEntity.value })),
        contentEntity: ref(null),
        inheritedSlotConfig,
    }),
}));

// The store module only exists to register `cmsPage` for real components; the spec stubs the store
// registry itself.
jest.mock('src/module/sw-cms/store/cms-page.store', () => ({}));

const updateElementConfig = jest.fn();
const getPropertyByMappingPath = jest.fn();
const elementRegistry: Record<string, unknown> = {};

function stubShopware(): void {
    window.Shopware = {
        Service: jest.fn(() => ({
            getCmsElementRegistry: () => elementRegistry,
            getPropertyByMappingPath,
        })),
        Store: { get: jest.fn(() => ({ updateElementConfig })) },
        Utils: { object: objectUtils },
    } as unknown as typeof Shopware;
}

function slot(overrides: Partial<RuntimeSlot> = {}): RuntimeSlot {
    return { id: 'slot-1', type: 'text', config: {}, data: {}, ...overrides } as RuntimeSlot;
}

describe('src/app/composables/use-cms-element', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        stubShopware();
        inheritedSlotConfig.value = null;
        currentDemoEntity.value = null;
        Object.keys(elementRegistry).forEach((key) => delete elementRegistry[key]);
        elementRegistry.text = { defaultConfig: { content: { source: 'static', value: 'default content' } } };
    });

    it('resolves the element type defaults without writing them into the element', () => {
        const element = slot();
        const { config } = useCmsElement({ element: () => element });

        expect(config.value.content.value).toBe('default content');
        expect(element.config).toEqual({});
    });

    it('keeps what the element configures and lets the caller`s defaults win over the registry', () => {
        const element = slot({ config: { content: { source: 'static', value: 'own' } } as unknown as CmsSlotConfig });
        const { config } = useCmsElement({
            element: () => element,
            defaultConfig: () => ({ headline: { source: 'static', value: 'from prop' } }),
        });

        expect(config.value.content.value).toBe('own');
        expect(config.value.headline.value).toBe('from prop');
    });

    it('falls back to the translated value of a config key the element does not carry', () => {
        const element = slot({
            translated: { config: { content: { source: 'static', value: 'translated' } } } as RuntimeSlot['translated'],
        });
        const { config } = useCmsElement({ element: () => element });

        expect(config.value.content.value).toBe('translated');
    });

    it('applies the inherited slot config of the element on top', () => {
        const element = slot({ config: { content: { source: 'static', value: 'own' } } as unknown as CmsSlotConfig });

        inheritedSlotConfig.value = {
            'slot-1': { content: { source: 'static', value: 'inherited' } },
        } as unknown as { [slotId: string]: CmsSlotConfig };

        const { config } = useCmsElement({ element: () => element });

        expect(config.value.content.value).toBe('inherited');

        // Resolved on read: the shared element object stays as the store wrote it.
        expect(element.config.content.value).toBe('own');
    });

    it('reads a config value by path', () => {
        const { getConfigValue } = useCmsElement({ element: () => slot() });

        expect(getConfigValue('content.value')).toBe('default content');
        expect(getConfigValue('headline.value')).toBeUndefined();
    });

    it('routes a config write through the cmsPage store action', () => {
        const { setConfigValue } = useCmsElement({ element: () => slot() });

        setConfigValue('content.value', 'new content');

        expect(updateElementConfig).toHaveBeenCalledWith('slot-1', 'content.value', 'new content');
    });

    it('reads a demo value off the current demo entity through the cms service', () => {
        currentDemoEntity.value = { id: 'product-1' };
        getPropertyByMappingPath.mockReturnValue('demo value');

        const { getDemoValue } = useCmsElement({ element: () => slot() });

        expect(getDemoValue('product.name')).toBe('demo value');
        expect(getPropertyByMappingPath).toHaveBeenCalledWith({ id: 'product-1' }, 'product.name');
    });
});
