import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from 'src/core/data/entity-collection.data';

import Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';
import template from './sw-category-detail-custom-entity.html.twig';
import './sw-category-detail-custom-entity.scss';

const { Utils } = Shopware;
const EXTENSION_POSTFIX = 'SwCategories';

/**
 * @private
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    data() {
        return {
            categoryCustomEntityProperty: '',
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
        customEntityAssignments(): EntityCollection<'custom_entity'> | undefined {
            return this.category?.extensions?.
                [`${this.categoryCustomEntityProperty}${EXTENSION_POSTFIX}`] as
                    EntityCollection<'custom_entity'> | undefined;
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

        category(): Entity<'category'> | null {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return Shopware.State.get('swCategoryDetail').category as Entity<'category'> | null;
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

    created(): void {
        void this.fetchCustomEntityName();
    },

    methods: {
        onAssignmentChange(customEntityAssignments: EntityCollection<'custom_entity'>): void {
            const categoryExtensions = this.category?.extensions;
            if (!categoryExtensions) {
                return;
            }

            categoryExtensions[`${this.categoryCustomEntityProperty}${EXTENSION_POSTFIX}`] = customEntityAssignments;
        },

        onEntityChange(id: string, entity?: Entity<'custom_entity'>) {
            if (!this.category) {
                return;
            }

            this.category.customEntityTypeId = id;

            this.categoryCustomEntityProperty = Utils.string.camelCase(entity?.name ?? '');
        },

        async fetchCustomEntityName(): Promise<void> {
            if (!this.category?.customEntityTypeId) {
                return;
            }

            const customEntityRepository = this.repositoryFactory.create('custom_entity');
            const customEntity = await customEntityRepository.get(this.category.customEntityTypeId);

            if (!customEntity) {
                return;
            }

            this.categoryCustomEntityProperty = Utils.string.camelCase(customEntity.name);
        },
    },
});
