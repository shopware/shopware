import template from './sw-cms-section.html.twig';
import './sw-cms-section.scss';

const { Component, Application } = Shopware;

Component.register('sw-cms-section', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    props: {
        page: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        section: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        currentBlock: {
            type: [Object, null],
            required: false,
            default: null
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
            const initContainer = Application.getContainer('init');
            const context = initContainer.contextService;
            let backgroundMedia = null;

            if (this.section.backgroundMedia) {
                if (this.section.backgroundMedia.id) {
                    backgroundMedia = `url("${this.section.backgroundMedia.url}")`;
                } else {
                    backgroundMedia = `url('${context.assetsPath}${this.section.backgroundMedia.url}')`;
                }
            }

            return {
                'background-color': this.section.backgroundColor || 'transparent',
                'background-image': backgroundMedia,
                'background-size': this.section.backgroundMediaMode
            };
        },

        overlayClasses() {
            return {
                'is--active': this.active
            };
        },

        toolbarClasses() {
            return {
                'is--active': this.active
            };
        },

        isSideBarType() {
            return this.section.type === 'sidebar';
        },

        sideBarEmpty() {
            return this.sideBarBlocks.length === 0;
        },

        mainContentEmpty() {
            return this.mainContentBlocks.length === 0;
        },

        sideBarBlocks() {
            return this.section.blocks.filter((block => block.sectionPosition === 'sidebar'));
        },

        mainContentBlocks() {
            return this.section.blocks.filter((block => block.sectionPosition !== 'sidebar'));
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
            this.$emit('page-config-open');
        },

        onSectionOverlayClick() {
            this.$emit('section-overlay-click');
        },

        onSectionDelete() {
            this.$emit('section-delete');
        },

        onSectionDuplicate(section) {
            this.$emit('section-duplicate', section);
        },

        onAddSectionBlock() {
            this.openBlockBar();
        },

        onBlockSelection(block) {
            this.openBlockBar();
            this.$emit('current-block-change', this.section.id, block);
        },

        onBlockDuplicate(block, section) {
            this.$emit('block-duplicate', block, section);
        },

        onBlockDelete(blockId) {
            this.section.blocks.remove(blockId);

            if (this.currentBlock && this.currentBlock.id === blockId) {
                this.$emit('current-block-change', this.section.id, null);
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
