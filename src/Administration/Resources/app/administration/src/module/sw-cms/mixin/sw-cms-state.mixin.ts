import { defineComponent } from 'vue';
import '../store/cms-page.store';

/**
 * @private
 * @package buyers-experience
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

                set(block: EntitySchema.Entity<'cms_block'>) {
                    this.cmsPageState.setSelectedBlock(block);
                },
            },

            selectedSection: {
                get() {
                    return this.cmsPageState.selectedSection;
                },

                set(section: EntitySchema.Entity<'cms_section'>) {
                    this.cmsPageState.setSelectedSection(section);
                },
            },

            currentDeviceView() {
                return this.cmsPageState.currentCmsDeviceView;
            },

            isSystemDefaultLanguage() {
                return this.cmsPageState.isSystemDefaultLanguage;
            },
        },
    }),
);
