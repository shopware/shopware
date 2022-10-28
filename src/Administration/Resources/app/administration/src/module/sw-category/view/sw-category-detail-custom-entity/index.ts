import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from 'src/core/data/entity-collection.data';

import Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';
import template from './sw-category-detail-custom-entity.html.twig';
import './sw-category-detail-custom-entity.scss';

interface CustomEntity extends Entity {
    name: string;
}

interface CategoryEntity extends Entity {
    customEntityTypeId: string | undefined;
    extensions: Record<string, EntityCollection | undefined>;
}

const { Component, Utils } = Shopware;
const EXTENSION_POSTFIX = 'CmsAwareCategories';

/**
 * @private
 */
Component.register('sw-category-detail-custom-entity', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    data() {
        return {
            categoryCustomEntityProperty: '' as string,
        };
    },

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        customEntityAssignments: {
            get(): EntityCollection | undefined {
                const categoryExtensions = this.category?.extensions;
                if (!categoryExtensions) {
                    return undefined;
                }

                return categoryExtensions[`${this.categoryCustomEntityProperty}${EXTENSION_POSTFIX}`];
            },
            set(customEntityAssignments: EntityCollection) {
                const categoryExtensions = this.category?.extensions;
                if (!categoryExtensions) {
                    return;
                }

                categoryExtensions[`${this.categoryCustomEntityProperty}${EXTENSION_POSTFIX}`] = customEntityAssignments;
            },
        },

        customEntityColumns(): { dataIndex: string; property: string, label: string }[] {
            return [
                {
                    dataIndex: 'cmsAwareTitle',
                    property: 'cmsAwareTitle',
                    label: this.$tc('sw-category.base.customEntity.instanceAssignment.title'),
                },
            ];
        },

        category(): CategoryEntity | null {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return Shopware.State.get('swCategoryDetail').category as CategoryEntity | null;
        },

        customEntityCriteria(): Criteria {
            return new Criteria(1, 10)
                .addFilter(Criteria.contains('flags', 'cms-aware'));
        },

        sortingCriteria(): Criteria {
            return new Criteria(1, 10)
                .addSorting(Criteria.sort('cmsAwareTitle', 'ASC'));
        },
    },

    created() {
        void this.fetchCustomEntityName();
    },


    methods: {
        onEntityChange(id: string, entity?: CustomEntity) {
            if (!this.category) {
                return;
            }

            this.category.customEntityTypeId = id;

            this.categoryCustomEntityProperty = Utils.string.camelCase(entity?.name ?? '');
        },

        async fetchCustomEntityName() {
            if (!this.category?.customEntityTypeId) {
                return;
            }

            const customEntityRepository = this.repositoryFactory.create('custom_entity');
            const customEntity = await customEntityRepository
                .get(this.category.customEntityTypeId) as CustomEntity | null;

            if (!customEntity) {
                return;
            }

            this.categoryCustomEntityProperty = Utils.string.camelCase(customEntity.name);
        },
    },
});
