/**
 * @private
 * @package buyers-experience
 */
Shopware.Mixin.register('cms-state', {
    computed: {
        cmsPageState() {
            return Shopware.Store.get('cmsPageState');
        },

        selectedBlock: {
            get() {
                return this.cmsPageState.selectedBlock;
            },

            set(block) {
                this.cmsPageState.setSelectedBlock(block);
            },
        },

        selectedSection: {
            get() {
                return this.cmsPageState.selectedSection;
            },

            set(section) {
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
});
