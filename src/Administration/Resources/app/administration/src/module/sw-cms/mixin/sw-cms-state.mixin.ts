import { defineComponent } from 'vue';
import '../store/cms-page.store';
import type { CmsSlotConfig } from '../service/cms.service';

const { cloneDeep } = Shopware.Utils.object;

type WithSlotConfig = {
    slotConfig?: {
        [slotId: string]: CmsSlotConfig;
    };
    translations?: Array<{
        languageId: string;
        slotConfig?: {
            [slotId: string]: CmsSlotConfig;
        };
    }>;
};

type ContentEntity<T extends keyof EntitySchema.Entities> = Entity<T> & WithSlotConfig;
/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Mixin.register(
    'cms-state',
    defineComponent({
        computed: {
            cmsPageState() {
                return Shopware.Store.get('cmsPage');
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
                    return Shopware.Store.get('swCategoryDetail')?.category as ContentEntity<'category'>;
                } catch {
                    return null;
                }
            },

            product() {
                try {
                    return Shopware.Store.get('swProductDetail')?.product as ContentEntity<'product'>;
                } catch {
                    return null;
                }
            },

            landingPage() {
                try {
                    return Shopware.Store.get('swCategoryDetail')?.landingPage as ContentEntity<'landing_page'>;
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
                const currentLanguageId = Shopware.Store.get('context').api.languageId;
                const parentLanguageId = Shopware.Store.get('context').api.language?.parentId;

                const currentSlotConfig = this.getSlotConfigForLanguage(currentLanguageId);
                const parentSlotConfig = parentLanguageId ? this.getSlotConfigForLanguage(parentLanguageId) : null;

                if (!currentSlotConfig && !parentSlotConfig) {
                    return null;
                }

                /**
                 * Merge field-by-field within each slot so a partial child-language override
                 * does not shadow parent-language fields on the same slot.
                 */
                const merged: { [slotId: string]: CmsSlotConfig } = {};

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
            },
        },
        methods: {
            getSlotConfigForLanguage(languageId?: string | null) {
                if (!languageId) {
                    return null;
                }

                if (languageId === Shopware.Store.get('context').api.languageId) {
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
