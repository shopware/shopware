import { defineComponent } from 'vue';
import '../store/cms-page.store';
import type { CmsSlotConfig } from '../service/cms.service';

type WithSlotConfig = {
    slotConfig?: {
        [slotId: string]: CmsSlotConfig;
    };
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
        },
    }),
);
