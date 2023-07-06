import type ChangesetGenerator from 'src/core/data/changeset-generator.data';
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

/**
 * @private
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'cmsPageTypeService',
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

    data(): {
        cmsPage: Entity<'cms_page'> | null,
        showLayoutSelection: boolean,
        isLoading: boolean
        } {
        return {
            cmsPage: null,
            showLayoutSelection: false,
            isLoading: false,
        };
    },

    computed: {
        cmsPageRepository(): Repository<'cms_page'> {
            return this.repositoryFactory.create('cms_page');
        },

        changesetGenerator(): ChangesetGenerator {
            return new Shopware.Data.ChangesetGenerator();
        },

        cmsPageCriteria(): Criteria {
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

        pageTypeTitle(name?: string): string {
            const fallback = this.$tc('sw-category.base.cms.defaultDesc');

            if (!name) {
                return fallback;
            }

            const pageType = this.cmsPageTypeService.getType(this.cmsPage?.type);
            return pageType ? this.$tc(pageType.title) : fallback;
        },
    },

    watch: {
        cmsPageId(): void {
            void this.getCmsPage();
        },

        cmsPage: {
            handler(_newCmsPage, oldCmsPage): void {
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
        openLayoutModal(): void {
            this.showLayoutSelection = true;
        },

        closeLayoutModal(): void {
            this.showLayoutSelection = false;
        },

        onLayoutSelect(selectedLayoutId: string | null): void {
            this.$emit('update:cms-page-id', selectedLayoutId);
        },

        openInCmsEditor(): void {
            if (!this.cmsPageId) {
                return;
            }

            void this.$router.push({ name: 'sw.cms.detail', params: { id: this.cmsPageId } });
        },

        createNewLayout(): void {
            this.$emit('create-layout');
        },

        applySlotOverrides(cmsPage: Entity<'cms_page'>): Entity<'cms_page'> {
            if (!this.slotOverrides) {
                return cmsPage;
            }

            cmsPage.sections?.forEach((section) => {
                section.blocks?.forEach((block) => {
                    block.slots?.forEach((slot) => {
                        const slotOverride = this.slotOverrides[slot.id];
                        if (!slotOverride) {
                            return;
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
            const cmsPage = this.applySlotOverrides(response[0]);

            Shopware.State.commit('cmsPageState/setCurrentPage', cmsPage);
            this.cmsPage = cmsPage;

            this.isLoading = false;
        },

        deleteSpecificKeys(sections: Entity<'cms_section'>[]): void {
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

                        Object.values(slot.config as Record<string, {
                            entity?: string,
                            required?: boolean,
                            type?: string
                        }>).forEach((configField) => {
                            if (!configField) {
                                return;
                            }

                            if (configField.entity) {
                                delete configField.entity;
                            }
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
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

            if (this.cmsPage.sections) {
                this.deleteSpecificKeys(this.cmsPage.sections);
            }

            const { changes } = this.changesetGenerator.generate(this.cmsPage) as { changes: Entity<'cms_page'> };

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
