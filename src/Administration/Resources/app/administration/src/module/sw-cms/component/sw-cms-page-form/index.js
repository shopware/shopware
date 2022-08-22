import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';
import CMS from '../../constant/sw-cms.constant';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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

        slotPositions() {
            return CMS.SLOT_POSITIONS;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.page.sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.slots.sort((a, b) => {
                        const positionA = this.slotPositions[a.slot] ?? this.slotPositions.default;
                        const positionB = this.slotPositions[b.slot] ?? this.slotPositions.default;

                        return positionA - positionB;
                    });
                });
            });
        },

        getBlockTitle(block) {
            if (typeof block.name === 'string' && block.name.length !== 0) {
                return block.name;
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
