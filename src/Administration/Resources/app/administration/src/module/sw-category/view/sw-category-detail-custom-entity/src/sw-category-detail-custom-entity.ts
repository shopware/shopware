import template from './sw-category-detail-custom-entity.html.twig';
import './sw-category-detail-custom-entity.scss';
import {
    aclService,
    camelCase,
    Criteria,
    getAssetFilter,
    repositoryFactoryService,
    useCategoryDetailStore,
    wrapComponentConfig,
} from '../dependencies';

const EXTENSION_POSTFIX = 'SwCategories';

/**
 * @private
 * @sw-package inventory
 */
export default wrapComponentConfig({
    template,

    inject: [repositoryFactoryService, aclService],

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
            return this.category?.extensions?.[`${this.categoryCustomEntityProperty}${EXTENSION_POSTFIX}`] as
                | EntityCollection<'custom_entity'>
                | undefined;
        },

        customEntityColumns(): {
            dataIndex: string;
            property: string;
            label: string;
        }[] {
            return [
                {
                    dataIndex: 'cmsAwareTitle',
                    property: 'cmsAwareTitle',
                    label: this.$t('sw-category.base.customEntity.instanceAssignment.title'),
                },
            ];
        },

        category(): Entity<'category'> | null {
            return useCategoryDetailStore().category as Entity<'category'> | null;
        },

        customEntityCriteria(): Criteria {
            return new Criteria(1, 10).addFilter(Criteria.contains('flags', 'cms-aware'));
        },

        sortingCriteria(): Criteria {
            return new Criteria(1, 10).addSorting(Criteria.sort('cmsAwareTitle', 'ASC'));
        },

        assetFilter() {
            return getAssetFilter();
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

        onEntityChange(id: EntityKey<'custom_entity'>, entity?: Entity<'custom_entity'>) {
            if (!this.category) {
                return;
            }

            this.category.customEntityTypeId = id;

            this.categoryCustomEntityProperty = camelCase(entity?.name ?? '');
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

            this.categoryCustomEntityProperty = camelCase(customEntity.name);
        },
    },
});
