import template from './sw-cms-section.html.twig';
import './sw-cms-section.scss';

const { Component, Mixin, Filter } = Shopware;

Component.register('sw-cms-section', {
    template,

    inject: [
        'cmsService',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('cms-state'),
    ],

    props: {
        page: {
            type: Object,
            required: true,
        },

        section: {
            type: Object,
            required: true,
        },

        active: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
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
                'is--active': this.active,
                'is--boxed': this.section.sizingMode === 'boxed',
            };
        },

        sectionTypeClass() {
            return `is--${this.section.type}`;
        },

        customSectionClass() {
            return this.section.cssClass;
        },

        sectionStyles() {
            let backgroundMedia = null;

            if (this.section.backgroundMedia) {
                if (this.section.backgroundMedia.id) {
                    backgroundMedia = `url("${this.section.backgroundMedia.url}")`;
                } else {
                    backgroundMedia = `url('${this.assetFilter(this.section.backgroundMedia.url)}')`;
                }
            }

            return {
                'background-color': this.section.backgroundColor || 'transparent',
                'background-image': backgroundMedia,
                'background-size': this.section.backgroundMediaMode,
            };
        },

        sectionSidebarClasses() {
            return {
                'is--empty': this.sideBarEmpty,
                'is--hidden': this.sectionMobileAndHidden,
            };
        },

        sectionMobileAndHidden() {
            const view = Shopware.State.get('cmsPageState').currentCmsDeviceView;
            return view === 'mobile' && this.section.mobileBehavior === 'hidden';
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
            const sideBarBlocks = this.section.blocks.filter((block => this.blockTypeExists(block.type)
                && block.sectionPosition === 'sidebar'));
            return sideBarBlocks.sort((a, b) => a.position - b.position);
        },

        mainContentBlocks() {
            const mainContentBlocks = this.section.blocks.filter((block => this.blockTypeExists(block.type)
                && block.sectionPosition !== 'sidebar'));
            return mainContentBlocks.sort((a, b) => a.position - b.position);
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        blockTypes() {
            return Object.keys(this.cmsService.getCmsBlockRegistry());
        },
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
            if (this.disabled) {
                return;
            }

            this.$emit('page-config-open', 'blocks');
        },

        onAddSectionBlock() {
            this.openBlockBar();
        },

        onBlockSelection(block) {
            Shopware.State.dispatch('cmsPageState/setBlock', block);
            this.$emit('page-config-open', 'itemConfig');
        },

        onBlockDuplicate(block, section) {
            this.$emit('block-duplicate', block, section);
        },

        onBlockDelete(blockId) {
            this.section.blocks.remove(blockId);

            if (this.selectedBlock && this.selectedBlock.id === blockId) {
                Shopware.State.commit('cmsPageState/removeSelectedBlock');
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
        },

        blockTypeExists(type) {
            return this.blockTypes.includes(type);
        },
    },
});
