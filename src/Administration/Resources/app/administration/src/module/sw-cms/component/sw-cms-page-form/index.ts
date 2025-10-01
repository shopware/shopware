import template from './sw-cms-page-form.html.twig';
import './sw-cms-page-form.scss';

const { cloneDeep, getObjectDiff } = Shopware.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['cmsService'],

    props: {
        page: {
            type: Object as PropType<Entity<'cms_page'>>,
            required: true,
        },
        entityConfig: {
            type: Object,
            required: false,
            default: null,
        },
        elementUpdate: {
            type: Function,
            required: false,
            default: () => {},
        },
    },

    data: () => {
        return {
            restoreBlockInheritanceIds: [] as string[],
        }
    },

    computed: {
        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        slotPositions() {
            return Shopware.Constants.CMS.SLOT_POSITIONS as { [key: string]: number };
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

        getBlockTitle(block: Entity<'cms_block'>) {
            if (typeof block.name === 'string' && block.name.length !== 0) {
                return block.name;
            }

            if (this.cmsBlocks[block.type]) {
                return this.cmsBlocks[block.type]!.label;
            }

            return '';
        },

        isInherited(block: Entity<'cms_block'>) {
            if (this.entityConfig === null || !block.slots) {
                return false;
            }

            for (const element of block.slots) {
                if (this.entityConfig.hasOwnProperty(element.id)) {
                    return false;
                }
            }

            return true;
        },

        restoreBlockInheritance(block: Entity<'cms_block'>) {
            if (!block.slots) {
                return;
            }

            let hasChanges = false;
            for (const element of block.slots) {
                const elementChanges = getObjectDiff(this.entityConfig[element.id], element.config);

                if (Object.keys(elementChanges).length) {
                    hasChanges = true;
                    break;
                }
            }

            if (!hasChanges) {
                for (const element of block.slots) {
                    delete this.entityConfig[element.id];
                }

                return;
            }

            for (const element of block.slots) {
                this.restoreBlockInheritanceIds.push(element.id);
            }
        },

        removeBlockInheritance(block: Entity<'cms_block'>) {
            if (!block.slots) {
                return;
            }

            for (const element of block.slots) {
                this.entityConfig[element.id] = cloneDeep(element.config) || {};
            }
        },

        slotConfig(slot: Entity<'cms_slot'>) {
            // Fallback to old behavior if no entityConfig is set, and directly mutate the reference
            if (this.entityConfig === null) {
                return slot;
            }

            const slotElement = cloneDeep(slot);

            if (this.entityConfig[slot.id]) {
                slotElement.config = this.entityConfig[slot.id];
            }

            const defaultConfig = this.cmsElements[slot.type]?.defaultConfig;
            if (defaultConfig instanceof Object && slotElement.config) {
                for (const configKey in defaultConfig) {
                    if (!slotElement.config.hasOwnProperty(configKey)) {
                        // @ts-expect-error
                        slotElement.config[configKey] = defaultConfig[configKey];
                    }
                }
            }

            return slotElement;
        },

        onConfirmRestoreBlockInheritance() {
            for (const elementId of this.restoreBlockInheritanceIds) {
                delete this.entityConfig[elementId];
            }

            this.restoreBlockInheritanceIds = [];
        },

        onCancelRestoreBlockInheritance() {
            this.restoreBlockInheritanceIds = [];
        },

        displaySectionType(block: Entity<'cms_block'>) {
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

        getSectionName(section: Entity<'cms_section'>) {
            if (section.name) {
                return section.name;
            }

            return section.type === 'sidebar' ? this.$tc('sw-cms.section.isSidebar') : this.$tc('sw-cms.section.isDefault');
        },

        getSectionPosition(block: Entity<'cms_block'>) {
            return block.sectionPosition === 'main'
                ? this.$tc('sw-cms.section.positionRight')
                : this.$tc('sw-cms.section.positionLeft');
        },

        getDeviceActive(viewport: string, section: Entity<'cms_section'>, block: Entity<'cms_block'> | null = null) {
            const sectionVisibility = section.visibility as {
                [key: string]: boolean;
            };
            const blockVisibility = block?.visibility as { [key: string]: boolean } | undefined;

            const isActive = blockVisibility
                ? sectionVisibility[viewport] && blockVisibility[viewport]
                : sectionVisibility[viewport];

            return isActive ? `regular-${viewport}` : `regular-${viewport}-slash`;
        },

        displayNotification(section: Entity<'cms_section'>, block: Entity<'cms_block'>) {
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
