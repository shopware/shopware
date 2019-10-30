import template from './sw-cms-section.html.twig';
import './sw-cms-section.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-section', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('cms-state')
    ],

    props: {
        page: {
            type: Object,
            required: true
        },

        section: {
            type: Object,
            required: true
        },

        active: {
            type: Boolean,
            required: false,
            default: false
        },

        isSystemDefaultLanguage: {
            type: Boolean,
            required: true
        }
    },

    computed: {
        blockRepository() {
            return this.repositoryFactory.create('cms_block');
        },

        slotRepository() {
            return this.repositoryFactory.create('cms_slot');
        },

        sectionClasses() {
            return {
                'is--boxed': this.section.sizingMode === 'boxed',
                'has--shadow-top': this.section.position === 0,
                'has--shadow-bottom': this.section.position === (this.page.sections.length - 1)
            };
        },

        sectionTypeClass() {
            return `is--${this.section.type}`;
        },

        customSectionClass() {
            return this.section.cssClass;
        },

        sectionStyles() {
            const apiContext = Shopware.Context.Api;
            let backgroundMedia = null;

            if (this.section.backgroundMedia) {
                if (this.section.backgroundMedia.id) {
                    backgroundMedia = `url("${this.section.backgroundMedia.url}")`;
                } else {
                    backgroundMedia = `url('${apiContext.assetsPath}${this.section.backgroundMedia.url}')`;
                }
            }

            return {
                'background-color': this.section.backgroundColor || 'transparent',
                'background-image': backgroundMedia,
                'background-size': this.section.backgroundMediaMode
            };
        },

        sectionSidebarClasses() {
            return {
                'is--empty': this.sideBarEmpty,
                'is--offcanvas': this.sectionMobileAndOffcanvas
            };
        },

        sectionMobileAndOffcanvas() {
            const view = this.$store.state.cmsPageState.currentCmsDeviceView;
            return view === 'mobile' && this.section.mobileBehavior === 'offcanvas';
        },

        isSideBarType() {
            return this.section.type === 'sidebar';
        },

        sideBarEmpty() {
            return this.sideBarBlocks.length === 0;
        },

        blockCount() {
            return this.section.blocks.length;
        },

        mainContentEmpty() {
            return this.mainContentBlocks.length === 0;
        },

        sideBarBlocks() {
            const sideBarBlocks = this.section.blocks.filter((block => block.sectionPosition === 'sidebar'));
            return sideBarBlocks.sort((a, b) => a.position - b.position);
        },

        mainContentBlocks() {
            const mainContentBlocks = this.section.blocks.filter((block => block.sectionPosition !== 'sidebar'));
            return mainContentBlocks.sort((a, b) => a.position - b.position);
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.section.backgroundMediaMode) {
                this.section.backgroundMediaMode = 'cover';
            }
        },

        openBlockBar() {
            this.$emit('page-config-open', 'blocks');
        },

        onSectionOverlayClick() {
            this.$emit('section-overlay-click');
        },

        onAddSectionBlock() {
            this.openBlockBar();
        },

        onBlockSelection(block) {
            this.$store.dispatch('cmsPageState/setBlock', block);
            this.$emit('page-config-open', 'itemConfig');
        },

        onBlockDuplicate(block, section) {
            this.$emit('block-duplicate', block, section);
        },

        onBlockDelete(blockId) {
            this.section.blocks.remove(blockId);

            if (this.selectedBlock && this.selectedBlock.id === blockId) {
                this.$store.commit('cmsPageState/removeSelectedBlock');
            }

            this.updateBlockPositions();
        },

        updateBlockPositions() {
            this.section.blocks.forEach((block, index) => {
                block.position = index;
            });
        },

        getDropData(index, sectionPosition = 'main') {
            return { dropIndex: index, section: this.section, sectionPosition };
        }
    }
});
