import { type PropType } from 'vue';
import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';
import CMS from '../../constant/sw-cms.constant';

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['cmsService'],

    props: {
        page: {
            type: Object as PropType<EntitySchema.Entity<'cms_page'>>,
            required: true,
        },
        elementUpdate: {
            type: Function,
            required: false,
            default: () => {},
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
            return CMS.SLOT_POSITIONS as { [key: string]: number };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.page.sections!.forEach((section) => {
                section.blocks!.forEach((block) => {
                    block.slots!.sort((a, b) => {
                        const positionA = this.slotPositions[a.slot] ?? this.slotPositions.default;
                        const positionB = this.slotPositions[b.slot] ?? this.slotPositions.default;

                        return positionA - positionB;
                    });

                    if (!block.visibility) {
                        block.visibility = {
                            mobile: true,
                            tablet: true,
                            desktop: true,
                        };
                    }
                });

                if (!section.visibility) {
                    section.visibility = {
                        mobile: true,
                        tablet: true,
                        desktop: true,
                    };
                }
            });
        },

        getBlockTitle(block: EntitySchema.Entity<'cms_block'>) {
            if (typeof block.name === 'string' && block.name.length !== 0) {
                return block.name;
            }

            if (this.cmsBlocks[block.type]) {
                return this.cmsBlocks[block.type]!.label;
            }

            return '';
        },

        displaySectionType(block: EntitySchema.Entity<'cms_block'>) {
            const blockSection = this.page.sections!.find((section) => section.id === block.sectionId);

            if (!blockSection) {
                return false;
            }

            const blocksInSameSection = blockSection.blocks!;
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

        getSectionName(section: EntitySchema.Entity<'cms_section'>) {
            if (section.name) {
                return section.name;
            }

            return section.type === 'sidebar' ? this.$tc('sw-cms.section.isSidebar') : this.$tc('sw-cms.section.isDefault');
        },

        getSectionPosition(block: EntitySchema.Entity<'cms_block'>) {
            return block.sectionPosition === 'main'
                ? this.$tc('sw-cms.section.positionRight')
                : this.$tc('sw-cms.section.positionLeft');
        },

        getDeviceActive(
            viewport: string,
            section: EntitySchema.Entity<'cms_section'>,
            block: EntitySchema.Entity<'cms_block'> | null = null,
        ) {
            const sectionVisibility = section.visibility as {
                [key: string]: boolean;
            };
            const blockVisibility = block?.visibility as { [key: string]: boolean } | undefined;

            const isActive = blockVisibility
                ? sectionVisibility[viewport] && blockVisibility[viewport]
                : sectionVisibility[viewport];

            return isActive ? `regular-${viewport}` : `regular-${viewport}-slash`;
        },

        displayNotification(section: EntitySchema.Entity<'cms_section'>, block: EntitySchema.Entity<'cms_block'>) {
            const sectionVisibility = section.visibility as {
                [key: string]: boolean;
            };
            const blockVisibility = block?.visibility as {
                [key: string]: boolean;
            };

            const isSectionDisplay = !(Object.values(sectionVisibility).indexOf(true) > -1);
            const isBlockDisplay = !(Object.values(blockVisibility).indexOf(true) > -1);

            return isSectionDisplay || isBlockDisplay;
        },
    },
});
