import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type Repository from 'src/core/data/repository.data';
import type { PropType } from 'vue';

import Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';
import template from './sw-generic-cms-page-assignment.html.twig';
import './sw-generic-cms-page-assignment.scss';

const objectUtils = Shopware.Utils.object;

interface CmsSlotOverrides {
    [key: string]: unknown
}

interface CmsSlotConfig {
    entity: string | undefined;
    required: boolean | undefined;
    type: string | undefined;
}

interface CmsSlot extends Entity {
    type: string;
    config: Record<string, CmsSlotConfig>;
}

interface CmsBlock extends Entity {
    slots: CmsSlot[];
}

interface CmsSection extends Entity {
    blocks: CmsBlock[];
}

interface CmsPage extends Entity {
    sections: CmsSection[];
}

/**
 * @private
 */
Shopware.Component.register('sw-generic-cms-page-assignment', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        cmsPageId: {
            type: String as PropType<string | null>,
            required: false,
            default: null,
        },

        slotOverrides: {
            type: Object as PropType<CmsSlotOverrides>,
            required: false,
            default: null,
        },

        allowedPageTypes: {
            type: Array as PropType<string[]>,
            required: false,
            default: () => [],
        },
    },

    data() {
        return {
            cmsPage: null as CmsPage | null,
            showLayoutSelection: false,
            isLoading: false,
        };
    },

    computed: {
        // eslint-disable-next-line camelcase
        cmsPageTypes(): { page: string, landingpage: string, product_list: string, product_detail: string } {
            return {
                page: this.$tc('sw-cms.detail.label.pageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.label.pageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.label.pageTypeCategory'),
                product_detail: this.$tc('sw-cms.detail.label.pageTypeProduct'),
            };
        },

        cmsPageRepository(): Repository {
            return this.repositoryFactory.create('cms_page');
        },

        changesetGenerator() {
            return new Shopware.Data.ChangesetGenerator();
        },

        cmsPageCriteria() {
            const criteria = new Criteria(1, 1);

            criteria
                .addAssociation('previewMedia')
                .getAssociation('sections')
                .addSorting(Criteria.sort('position'))
                .getAssociation('blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('slots');

            return criteria;
        },
    },

    watch: {
        cmsPageId() {
            void this.getCmsPage();
        },

        cmsPage: {
            handler(_newCmsPage, oldCmsPage) {
                if (oldCmsPage) {
                    this.emitCmsPageOverrides();
                }
            },
            deep: true,
        },
    },

    created() {
        void this.getCmsPage();
    },

    methods: {
        openLayoutModal() {
            this.showLayoutSelection = true;
        },

        closeLayoutModal() {
            this.showLayoutSelection = false;
        },

        onLayoutSelect(selectedLayoutId: string | null) {
            this.$emit('update:cms-page-id', selectedLayoutId);
        },

        openInCmsEditor() {
            if (!this.cmsPageId) {
                return;
            }

            this.$router.push({ name: 'sw.cms.detail', params: { id: this.cmsPageId } });
        },

        createNewLayout() {
            this.$emit('create-layout');
        },

        applySlotOverrides(cmsPage: CmsPage) {
            if (!this.slotOverrides) {
                return cmsPage;
            }

            cmsPage.sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.slots.forEach((slot) => {
                        const slotOverride = this.slotOverrides[slot.id];
                        if (!slotOverride) {
                            return;
                        }
                        if (slot.config === null) {
                            slot.config = {};
                        }
                        objectUtils.merge(slot.config, objectUtils.cloneDeep(slotOverride));
                    });
                });
            });

            return cmsPage;
        },

        async getCmsPage(): Promise<void> {
            if (this.cmsPageId === null) {
                this.cmsPage = null;
                return;
            }

            this.isLoading = true;

            const criteria = this.cmsPageCriteria;
            criteria.setIds([this.cmsPageId]);

            const response = await this.cmsPageRepository.search(criteria);
            const cmsPage = this.applySlotOverrides(response[0] as CmsPage);

            Shopware.State.commit('cmsPageState/setCurrentPage', cmsPage);
            this.cmsPage = cmsPage;

            this.isLoading = false;
        },

        deleteSpecificKeys(sections: CmsSection[]) {
            if (!sections) {
                return;
            }

            sections.forEach((section) => {
                if (!section.blocks) {
                    return;
                }

                section.blocks.forEach((block) => {
                    if (!block.slots) {
                        return;
                    }

                    block.slots.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.hasOwnProperty('required')) {
                                delete configField.required;
                            }
                            if (configField.type) {
                                delete configField.type;
                            }
                        });
                    });
                });
            });
        },

        emitCmsPageOverrides(): void {
            if (this.cmsPage === null) {
                return;
            }

            this.deleteSpecificKeys(this.cmsPage.sections);

            const { changes } = this.changesetGenerator.generate(this.cmsPage) as { changes: CmsPage };

            const slotOverrides = {} as Record<string, unknown>;
            if (!changes) {
                return;
            }

            if (Array.isArray(changes.sections)) {
                changes.sections.forEach((section) => {
                    if (Array.isArray(section.blocks)) {
                        section.blocks.forEach((block) => {
                            if (Array.isArray(block.slots)) {
                                block.slots.forEach((slot) => {
                                    slotOverrides[slot.id] = slot.config;
                                });
                            }
                        });
                    }
                });
            }

            this.$emit('update:slot-overrides', slotOverrides);
        },
    },
});
