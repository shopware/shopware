const { Mixin } = Shopware;

Mixin.register('cms-state', {
    computed: {
        cmsPageState() {
            return Shopware.State.get('cmsPageState');
        },

        selectedBlock: {
            get() {
                return this.cmsPageState.selectedBlock;
            },

            set(block) {
                this.$store.commit('cmsPageState/setSelectedBlock', block);
            },
        },

        selectedSection: {
            get() {
                return this.cmsPageState.selectedSection;
            },

            set(section) {
                this.$store.commit('cmsPageState/setSelectedSection', section);
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
