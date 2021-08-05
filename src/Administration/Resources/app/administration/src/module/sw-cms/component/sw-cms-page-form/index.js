import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';

const { Component } = Shopware;

Component.register('sw-cms-page-form', {
    template,

    inject: ['cmsService'],

    props: {
        page: {
            type: Object,
            required: true,
        },
    },

    computed: {
        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },
    },

    created() {
        const twoColumnSorting = ['left', 'right'];

        this.page.sections.forEach((section) => {
            section.blocks.forEach((block) => {
                if (block.type === 'text-two-column') {
                    block.slots.sort((a, b) => twoColumnSorting.indexOf(a.slot) - twoColumnSorting.indexOf(b.slot));
                }
            });
        });
    },

    methods: {
        getBlockTitle(block) {
            if (block.config?.name) {
                return block.config.name;
            }

            if (typeof this.cmsBlocks[block.type] !== 'undefined') {
                return this.cmsBlocks[block.type].label;
            }

            return '';
        },

        displaySectionType(block) {
            const blocksInSameSection = this.page.sections.find((section) => section.id === block.sectionId).blocks;
            const blocksNotInSamePosition = blocksInSameSection.filter((b) => {
                return b.sectionPosition !== block.sectionPosition;
            }).length;

            if (blocksNotInSamePosition === 0) {
                return false;
            }

            const blocksInSamePosition = blocksInSameSection.filter((b) => b.sectionPosition === block.sectionPosition);

            const firstBlockInPosition = blocksInSamePosition.reduce((firstBlock, actualBlock) => {
                return actualBlock.position < firstBlock.position ? actualBlock : firstBlock;
            }, block);

            return firstBlockInPosition.id === block.id;
        },

        getSectionName(section) {
            if (section.name) {
                return section.name;
            }

            return section.type === 'sidebar' ?
                this.$tc('sw-cms.section.isSidebar') :
                this.$tc('sw-cms.section.isDefault');
        },

        getSectionPosition(block) {
            return block.sectionPosition === 'main' ?
                this.$tc('sw-cms.section.positionRight') :
                this.$tc('sw-cms.section.positionLeft');
        },
    },
});
