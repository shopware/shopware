import { defineComponent } from 'vue';
import '../store/cms-page.store';
import type { CmsSlotConfig } from '../service/cms.service';
import { cloneDeep } from 'shopware:utils/object';
import useCmsPageStore from 'shopware:stores/cmsPage';
import useContextStore from 'shopware:stores/context';
import useSwCategoryDetailStore from 'shopware:stores/swCategoryDetail';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

type WithSlotConfig = {
    slotConfig?: {
        [slotId: EntityKey<'cms_slot'>]: CmsSlotConfig;
    };
    translations?: Array<{
        languageId: EntityKey<'language'>;
        slotConfig?: {
            [slotId: EntityKey<'cms_slot'>]: CmsSlotConfig;
        };
    }>;
};

type ContentEntity<T extends keyof EntitySchema.Entities> = Entity<T> & WithSlotConfig;
/**
 * @private
 * @sw-package discovery
 *
 * Duplicated in `src/app/composables/use-cms-state`; change both together.
 */
export default Shopware.Mixin.register(
    'cms-state',
    defineComponent({
        computed: {
            cmsPageState() {
                return useCmsPageStore();
            },

            selectedBlock: {
                get() {
                    return this.cmsPageState.selectedBlock;
                },

                set(block: Entity<'cms_block'>) {
                    this.cmsPageState.setSelectedBlock(block);
                },
            },

            selectedSection: {
                get() {
                    return this.cmsPageState.selectedSection;
                },

                set(section: Entity<'cms_section'>) {
                    this.cmsPageState.setSelectedSection(section);
                },
            },

            currentDeviceView() {
                return this.cmsPageState.currentCmsDeviceView;
            },

            isSystemDefaultLanguage() {
                return this.cmsPageState.isSystemDefaultLanguage;
            },

            category() {
                try {
                    return useSwCategoryDetailStore()?.category as ContentEntity<'category'>;
                } catch {
                    return null;
                }
            },

            product() {
                try {
                    return useSwProductDetailStore()?.product as ContentEntity<'product'>;
                } catch {
                    return null;
                }
            },

            landingPage() {
                try {
                    return useSwCategoryDetailStore()?.landingPage as ContentEntity<'landing_page'>;
                } catch {
                    return null;
                }
            },

            contentEntity() {
                const name = this.$route.name?.toString() || '';

                if (name.startsWith('sw.category.landingPageDetail')) {
                    return this.landingPage;
                }

                if (name.startsWith('sw.category.')) {
                    return this.category;
                }

                if (name.startsWith('sw.product.')) {
                    return this.product;
                }

                return null;
            },

            inheritedSlotConfig() {
                const currentLanguageId = useContextStore().api.languageId;
                const parentLanguageId = useContextStore().api.language?.parentId ?? useContextStore().api.systemLanguageId;

                const currentSlotConfig = this.getSlotConfigForLanguage(currentLanguageId);
                const parentSlotConfig = parentLanguageId ? this.getSlotConfigForLanguage(parentLanguageId) : null;

                if (!currentSlotConfig && !parentSlotConfig) {
                    return null;
                }

                /**
                 * Merge field-by-field within each slot so a partial child-language override
                 * does not shadow parent-language fields on the same slot.
                 */
                const merged: { [slotId: EntityKey<'cms_slot'>]: CmsSlotConfig } = {};

                for (const [
                    slotId,
                    fields,
                ] of Object.entries(parentSlotConfig ?? {})) {
                    merged[slotId as EntityKey<'cms_slot'>] = { ...fields };
                }

                for (const [
                    slotId,
                    fields,
                ] of Object.entries(currentSlotConfig ?? {})) {
                    merged[slotId as EntityKey<'cms_slot'>] = {
                        ...(merged[slotId as EntityKey<'cms_slot'>] ?? {}),
                        ...fields,
                    };
                }

                return cloneDeep(merged);
            },
        },
        methods: {
            getSlotConfigForLanguage(languageId?: EntityKey<'language'> | null) {
                if (!languageId) {
                    return null;
                }

                if (languageId === useContextStore().api.languageId) {
                    return this.contentEntity?.slotConfig ?? null;
                }

                const translation = this.contentEntity?.translations?.find((entityTranslation) => {
                    return entityTranslation.languageId === languageId;
                });

                return translation?.slotConfig ?? null;
            },
        },
    }),
);
