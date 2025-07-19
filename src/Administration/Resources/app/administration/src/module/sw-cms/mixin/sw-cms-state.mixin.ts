import { defineComponent } from 'vue';
import '../store/cms-page.store';

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
        },
    }),
);
