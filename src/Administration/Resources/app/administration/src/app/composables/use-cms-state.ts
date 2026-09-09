/**
 * @sw-package discovery
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, type ComputedRef, type WritableComputedRef } from 'vue';
import { useRoute } from 'vue-router';
import 'src/module/sw-cms/store/cms-page.store';
import type { CmsSlotConfig } from 'src/module/sw-cms/service/cms.service';

type SlotConfigMap = { [slotId: string]: CmsSlotConfig };

type WithSlotConfig = {
    slotConfig?: SlotConfigMap;
    translations?: Array<{
        languageId: string;
        slotConfig?: SlotConfigMap;
    }>;
};

type ContentEntity<T extends keyof EntitySchema.Entities> = Entity<T> & WithSlotConfig;

type CmsPageStore = PiniaRootState['cmsPage'];

/**
 * Composable alternative to the `cms-state` mixin: the CMS editor state a block, section or config
 * panel works against, read from the `cmsPage` store and the current route. The mixin stays in place
 * for Options API components.
 *
 * `selectedBlock` and `selectedSection` are writable: the mixin exposed them as get/set computeds so a
 * component could assign the selection, and the store actions behind them are the same.
 *
 * Keep this and `src/module/sw-cms/mixin/sw-cms-state.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useCmsState(): {
    cmsPageState: ComputedRef<CmsPageStore>;
    selectedBlock: WritableComputedRef<Entity<'cms_block'> | null>;
    selectedSection: WritableComputedRef<Entity<'cms_section'> | null>;
    currentDeviceView: ComputedRef<CmsPageStore['currentCmsDeviceView']>;
    isSystemDefaultLanguage: ComputedRef<boolean>;
    category: ComputedRef<ContentEntity<'category'> | null>;
    product: ComputedRef<ContentEntity<'product'> | null>;
    landingPage: ComputedRef<ContentEntity<'landing_page'> | null>;
    contentEntity: ComputedRef<ContentEntity<keyof EntitySchema.Entities> | null>;
    inheritedSlotConfig: ComputedRef<SlotConfigMap | null>;
    getSlotConfigForLanguage: (languageId?: string | null) => SlotConfigMap | null;
} {
    const route = useRoute();

    const cmsPageState = computed(() => Shopware.Store.get('cmsPage'));

    const selectedBlock = computed<Entity<'cms_block'> | null>({
        get: () => cmsPageState.value.selectedBlock,
        set: (block) => cmsPageState.value.setSelectedBlock(block as Entity<'cms_block'>),
    });

    const selectedSection = computed<Entity<'cms_section'> | null>({
        get: () => cmsPageState.value.selectedSection,
        set: (section) => cmsPageState.value.setSelectedSection(section as Entity<'cms_section'>),
    });

    const currentDeviceView = computed(() => cmsPageState.value.currentCmsDeviceView);

    const isSystemDefaultLanguage = computed(() => cmsPageState.value.isSystemDefaultLanguage);

    // The detail stores only exist while their module is loaded, so reading one outside it throws.
    const category = computed(() => {
        try {
            return (Shopware.Store.get('swCategoryDetail')?.category as ContentEntity<'category'>) ?? null;
        } catch {
            return null;
        }
    });

    const product = computed(() => {
        try {
            return (Shopware.Store.get('swProductDetail')?.product as ContentEntity<'product'>) ?? null;
        } catch {
            return null;
        }
    });

    const landingPage = computed(() => {
        try {
            return (Shopware.Store.get('swCategoryDetail')?.landingPage as ContentEntity<'landing_page'>) ?? null;
        } catch {
            return null;
        }
    });

    const contentEntity = computed<ContentEntity<keyof EntitySchema.Entities> | null>(() => {
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

        // Merge field-by-field within each slot so a partial child-language override does not shadow
        // parent-language fields on the same slot.
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

        return Shopware.Utils.object.cloneDeep(merged);
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
