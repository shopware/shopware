import template from './sw-promotion-v2-conditions.html.twig';

const { Criteria, EntityCollection } = Shopware.Data;
const types = Shopware.Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'ruleConditionDataProviderService',
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },
    },

    data() {
        return {
            excludedPromotions: this.createPromotionCollection(),
            personaRestrictedRules: [],
            orderRestrictedRules: [],
            cartRestrictedRules: [],
        };
    },

    computed: {
        exclusionCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.promotion.id)]));
            return criteria;
        },

        personaRuleFilter() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('conditions')
                .addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        cartConditionsRuleFilter() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('conditions')
                .addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        orderConditionsFilter() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('conditions')
                .addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadExclusions();
        },

        loadExclusions() {
            if (types.isEmpty(this.promotion.exclusionIds)) {
                this.excludedPromotions = this.createPromotionCollection();
                return;
            }

            const promotionRepository = this.repositoryFactory.create('promotion');
            const criteria = (new Criteria(1, 25)).addFilter(Criteria.equalsAny('id', this.promotion.exclusionIds));

            promotionRepository.search(criteria).then((excluded) => {
                this.excludedPromotions = excluded;
            });
        },

        onChangeExclusions(promotions) {
            this.promotion.exclusionIds = promotions.map((promotion) => {
                return promotion.id;
            });

            this.loadExclusions();
        },

        createPromotionCollection() {
            return new EntityCollection('/promotion', 'promotion', Shopware.Context.api, new Criteria(1, 25));
        },
    },
};
