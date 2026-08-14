/**
 * @sw-package discovery
 */
import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { useRoute } from 'vue-router';
import '../store/cms-page.store';
import type { CmsSlotConfig } from '../service/cms.service';

const { cloneDeep } = Shopware.Utils.object;

type SlotConfigMap = {
    [slotId: string]: CmsSlotConfig;
};

/** @private */
export type CmsContentEntity<T extends keyof EntitySchema.Entities> = Entity<T> & {
    slotConfig?: SlotConfigMap;
    translations?: Array<{
        languageId: string;
        slotConfig?: SlotConfigMap;
    }>;
};

/** @private */
export type AnyCmsContentEntity =
    | CmsContentEntity<'category'>
    | CmsContentEntity<'product'>
    | CmsContentEntity<'landing_page'>;

/** @private */
export interface UseCmsStateReturn {
    cmsPageState: ComputedRef<PiniaRootState['cmsPage']>;
    selectedBlock: WritableComputedRef<Entity<'cms_block'> | null>;
    selectedSection: WritableComputedRef<Entity<'cms_section'> | null>;
    currentDeviceView: ComputedRef<PiniaRootState['cmsPage']['currentCmsDeviceView']>;
    isSystemDefaultLanguage: ComputedRef<boolean>;
    category: ComputedRef<CmsContentEntity<'category'> | null>;
    product: ComputedRef<CmsContentEntity<'product'> | null>;
    landingPage: ComputedRef<CmsContentEntity<'landing_page'> | null>;
    contentEntity: ComputedRef<AnyCmsContentEntity | null>;
    inheritedSlotConfig: ComputedRef<SlotConfigMap | null>;
    getSlotConfigForLanguage: (languageId?: string | null) => SlotConfigMap | null;
}

/**
 * Composable alternative to the `cms-state` mixin: exposes the CMS page store and
 * the slot config of the entity the current route edits. The mixin read the route
 * from `this.$route`; here it comes from `useRoute()`, so the composable needs
 * nothing from the component instance.
 *
 * Keep this and `src/module/sw-cms/mixin/sw-cms-state.mixin.ts` in sync — change both together.
 *
 * @private
 */
export function useCmsState(): UseCmsStateReturn {
    const route = useRoute();

    const cmsPageState = computed(() => Shopware.Store.get('cmsPage'));

    const selectedBlock = computed({
        get: () => cmsPageState.value.selectedBlock,
        set: (block: Entity<'cms_block'>) => cmsPageState.value.setSelectedBlock(block),
    });

    const selectedSection = computed({
        get: () => cmsPageState.value.selectedSection,
        set: (section: Entity<'cms_section'>) => cmsPageState.value.setSelectedSection(section),
    });

    const currentDeviceView = computed(() => cmsPageState.value.currentCmsDeviceView);

    const isSystemDefaultLanguage = computed(() => cmsPageState.value.isSystemDefaultLanguage);

    const category = computed(() => {
        try {
            return (Shopware.Store.get('swCategoryDetail')?.category as CmsContentEntity<'category'>) ?? null;
        } catch {
            return null;
        }
    });

    const product = computed(() => {
        try {
            return (Shopware.Store.get('swProductDetail')?.product as CmsContentEntity<'product'>) ?? null;
        } catch {
            return null;
        }
    });

    const landingPage = computed(() => {
        try {
            return (Shopware.Store.get('swCategoryDetail')?.landingPage as CmsContentEntity<'landing_page'>) ?? null;
        } catch {
            return null;
        }
    });

    const contentEntity = computed<AnyCmsContentEntity | null>(() => {
        const name = route.name?.toString() || '';

        if (name.startsWith('sw.category.landingPageDetail')) {
            return landingPage.value;
        }

        if (name.startsWith('sw.category.')) {
            return category.value;
        }

        if (name.startsWith('sw.product.')) {
            return product.value;
        }

        return null;
    });

    function getSlotConfigForLanguage(languageId?: string | null): SlotConfigMap | null {
        if (!languageId) {
            return null;
        }

        if (languageId === Shopware.Store.get('context').api.languageId) {
            return contentEntity.value?.slotConfig ?? null;
        }

        const translation = contentEntity.value?.translations?.find((entityTranslation) => {
            return entityTranslation.languageId === languageId;
        });

        return translation?.slotConfig ?? null;
    }

    const inheritedSlotConfig = computed<SlotConfigMap | null>(() => {
        const currentLanguageId = Shopware.Store.get('context').api.languageId;
        const parentLanguageId = Shopware.Store.get('context').api.language?.parentId;

        const currentSlotConfig = getSlotConfigForLanguage(currentLanguageId);
        const parentSlotConfig = parentLanguageId ? getSlotConfigForLanguage(parentLanguageId) : null;

        if (!currentSlotConfig && !parentSlotConfig) {
            return null;
        }

        /**
         * Merge field-by-field within each slot so a partial child-language override
         * does not shadow parent-language fields on the same slot.
         */
        const merged: SlotConfigMap = {};

        for (const [
            slotId,
            fields,
        ] of Object.entries(parentSlotConfig ?? {})) {
            merged[slotId] = { ...fields };
        }

        for (const [
            slotId,
            fields,
        ] of Object.entries(currentSlotConfig ?? {})) {
            merged[slotId] = { ...(merged[slotId] ?? {}), ...fields };
        }

        return cloneDeep(merged);
    });

    return {
        cmsPageState,
        selectedBlock,
        selectedSection,
        currentDeviceView,
        isSystemDefaultLanguage,
        category,
        product,
        landingPage,
        contentEntity,
        inheritedSlotConfig,
        getSlotConfigForLanguage,
    };
}
