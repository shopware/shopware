/**
 * @sw-package discovery
 */
import { computed, ref } from 'vue';
import objectUtils from 'src/core/service/utils/object.utils';
import typesUtils from 'src/core/service/utils/types.utils';
import useCmsElementDeprecated from './use-cms-element-deprecated';
import type { CmsSlotConfig, RuntimeSlot } from 'src/module/sw-cms/service/cms.service';

const contentEntity = ref<unknown>(null);
const inheritedSlotConfig = ref<{ [slotId: string]: CmsSlotConfig } | null>(null);
const currentDemoEntity = ref<unknown>(null);

jest.mock('./use-cms-state', () => ({
    __esModule: true,
    default: () => ({
        cmsPageState: computed(() => ({ currentDemoEntity: currentDemoEntity.value })),
        contentEntity,
        inheritedSlotConfig,
        selectedBlock: ref(null),
    }),
}));

const getPropertyByMappingPath = jest.fn();
const elementRegistry: Record<string, unknown> = {};

function stubShopware(): void {
    window.Shopware = {
        Service: jest.fn(() => ({
            getCmsElementRegistry: () => elementRegistry,
            getPropertyByMappingPath,
        })),
        Utils: { object: objectUtils, types: typesUtils },
    } as unknown as typeof Shopware;
}

function slot(overrides: Partial<RuntimeSlot> = {}): RuntimeSlot {
    return { id: 'slot-1', type: 'text', config: {}, data: {}, ...overrides } as RuntimeSlot;
}

describe('src/app/composables/use-cms-element-deprecated', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        stubShopware();
        contentEntity.value = null;
        inheritedSlotConfig.value = null;
        currentDemoEntity.value = null;
        Object.keys(elementRegistry).forEach((key) => delete elementRegistry[key]);
        elementRegistry.text = {
            defaultConfig: { content: { source: 'static', value: 'default content' } },
            defaultData: { content: 'default data' },
        };
    });

    it('writes the base config into the element object the caller passed in', () => {
        const element = slot();
        const { initBaseConfig } = useCmsElementDeprecated({ element: () => element });

        initBaseConfig();

        // The element is the slot the cmsPage store owns: the write has to reach that object itself.
        expect(element.config).toEqual({ content: { source: 'static', value: 'default content' } });
    });

    it('merges the registry defaults under the caller`s defaultConfig', () => {
        const element = slot();
        const { initBaseConfig } = useCmsElementDeprecated({
            element: () => element,
            defaultConfig: () => ({ content: { source: 'static', value: 'from prop' } }),
        });

        initBaseConfig();

        expect(element.config.content.value).toBe('from prop');
    });

    it('keeps a config key the element already carries and prefers its translated value', () => {
        const element = slot({
            config: { content: { source: 'static', value: 'existing' } } as unknown as CmsSlotConfig,
            translated: { config: { headline: { source: 'static', value: 'translated' } } } as RuntimeSlot['translated'],
        });
        elementRegistry.text = {
            defaultConfig: {
                content: { source: 'static', value: 'default content' },
                headline: { source: 'static', value: 'default headline' },
            },
        };
        const { initBaseConfig } = useCmsElementDeprecated({ element: () => element });

        initBaseConfig();

        expect(element.config.content.value).toBe('existing');
        expect(element.config.headline.value).toBe('translated');
    });

    it('has no base config to write for an element without a type', () => {
        const element = slot({ type: undefined });
        const { initBaseConfig } = useCmsElementDeprecated({ element: () => element });

        initBaseConfig();

        expect(element.config).toEqual({});
    });

    it('applies the inherited slot config of the content entity on top', () => {
        const element = slot({ config: { content: { source: 'static', value: 'own' } } as unknown as CmsSlotConfig });
        const override = { source: 'static', value: 'inherited' };

        contentEntity.value = { id: 'category-1' };
        inheritedSlotConfig.value = { 'slot-1': { content: override } } as unknown as { [slotId: string]: CmsSlotConfig };

        const { applyContentOverride } = useCmsElementDeprecated({ element: () => element });

        applyContentOverride();

        expect(element.config.content).toEqual(override);

        // Cloned, so editing the element never writes back into the parent language's slot config.
        expect(element.config.content).not.toBe(override);
    });

    it('leaves the config alone when nothing inherits into this element', () => {
        const element = slot({ config: { content: { source: 'static', value: 'own' } } as unknown as CmsSlotConfig });

        contentEntity.value = { id: 'category-1' };
        inheritedSlotConfig.value = { 'other-slot': { content: { source: 'static', value: 'inherited' } } } as unknown as {
            [slotId: string]: CmsSlotConfig;
        };

        const { applyContentOverride } = useCmsElementDeprecated({ element: () => element });

        applyContentOverride();

        expect(element.config.content.value).toBe('own');
    });

    it('initializes the config from the defaults and the content override at once', () => {
        const element = slot();

        contentEntity.value = { id: 'category-1' };
        inheritedSlotConfig.value = {
            'slot-1': { content: { source: 'static', value: 'inherited' } },
        } as unknown as { [slotId: string]: CmsSlotConfig };

        const { initElementConfig } = useCmsElementDeprecated({ element: () => element });

        initElementConfig();

        expect(element.config.content.value).toBe('inherited');
    });

    it('fills the element data from the registry default data', () => {
        const element = slot({ data: {} as RuntimeSlot['data'] });
        const { initElementData } = useCmsElementDeprecated({ element: () => element });

        initElementData('text');

        expect(element.data).toEqual({ content: 'default data' });
    });

    it('keeps element data the element already carries', () => {
        const element = slot({ data: { content: 'own data' } as unknown as RuntimeSlot['data'] });
        const { initElementData } = useCmsElementDeprecated({ element: () => element });

        initElementData('text');

        expect(element.data).toEqual({ content: 'own data' });
    });

    it('reads a demo value off the current demo entity through the cms service', () => {
        currentDemoEntity.value = { id: 'product-1' };
        getPropertyByMappingPath.mockReturnValue('demo value');

        const { getDemoValue } = useCmsElementDeprecated({ element: () => slot() });

        expect(getDemoValue('product.name')).toBe('demo value');
        expect(getPropertyByMappingPath).toHaveBeenCalledWith({ id: 'product-1' }, 'product.name');
    });

    it('returns the cms state members the mixin chain provided', () => {
        const { cmsPageState, contentEntity: entity, cmsElements } = useCmsElementDeprecated({ element: () => slot() });

        expect(cmsPageState.value).toBeDefined();
        expect(entity.value).toBeNull();
        expect(cmsElements.value.text).toBeDefined();
    });
});
