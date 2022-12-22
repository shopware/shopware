import './sw-promotion-v2-cart-condition-form.scss';
import template from './sw-promotion-v2-cart-condition-form.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'promotionSyncService',
        'feature',
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },

        restrictedRules: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
    },
    data() {
        return {
            packagerKeys: [],
            sorterKeys: [],
        };
    },
    computed: {
        promotionGroupRepository() {
            return this.repositoryFactory.create('promotion_setgroup');
        },

        ruleFilter() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('conditions')
                .addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        packagers() {
            const result = [];

            this.packagerKeys.forEach((keyValue) => {
                result.push(
                    {
                        key: keyValue,
                        name: this.$tc(`sw-promotion-v2.detail.conditions.setgroups.packager.${keyValue}`),
                    },
                );
            });
            return result;
        },

        sorters() {
            const result = [];

            this.sorterKeys.forEach((keyValue) => {
                result.push(
                    {
                        key: keyValue,
                        name: this.$tc(`sw-promotion-v2.detail.conditions.setgroups.sorter.${keyValue}`),
                    },
                );
            });

            return result;
        },

        isEditingDisabled() {
            return (this.promotion === null || !this.acl.can('promotion.editor'));
        },
    },

    watch: {
        promotion() {
            this.loadSetGroups();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.promotion) {
                this.loadSetGroups();
            }

            this.promotionSyncService.loadPackagers().then((keys) => {
                this.packagerKeys = keys;
            });

            this.promotionSyncService.loadSorters().then((keys) => {
                this.sorterKeys = keys;
            });
        },

        loadSetGroups() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.equals('promotionId', this.promotion.id),
            );

            this.promotionGroupRepository.search(criteria).then((groups) => {
                this.promotion.setgroups = groups;
            });
        },

        addSetGroup() {
            const newGroup = this.promotionGroupRepository.create();
            newGroup.promotionId = this.promotion.id;
            newGroup.value = 2;
            newGroup.packagerKey = 'COUNT';
            newGroup.sorterKey = 'PRICE_ASC';

            this.promotion.setgroups.push(newGroup);
        },

        duplicateSetGroup(group) {
            const newGroup = this.promotionGroupRepository.create();
            newGroup.promotionId = group.promotionId;
            newGroup.value = group.value;
            newGroup.packagerKey = group.packagerKey;
            newGroup.sorterKey = group.sorterKey;

            this.promotion.setgroups.push(newGroup);
        },

        deleteSetGroup(group) {
            // add to delete list for the save process
            const deleteIds = Shopware.State.get('swPromotionDetail').setGroupIdsDelete;
            deleteIds.push(group.id);
            Shopware.State.commit('swPromotionDetail/setSetGroupIdsDelete', deleteIds);

            // remove also from entity for the view rendering
            this.promotion.setgroups = this.promotion.setgroups.filter((setGroup) => {
                return setGroup.id !== group.id;
            });
        },
    },
};
