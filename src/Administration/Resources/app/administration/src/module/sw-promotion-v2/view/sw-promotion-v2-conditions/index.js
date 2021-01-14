import template from './sw-promotion-v2-conditions.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const types = Shopware.Utils.types;

Component.register('sw-promotion-v2-conditions', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default() {
                return null;
            }
        }
    },

    computed: {
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.promotion.id)]));
            return criteria;
        }
    },

    data() {
        return {
            excludedPromotions: this.createPromotionCollection()
        };
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
            const criteria = (new Criteria()).addFilter(Criteria.equalsAny('id', this.promotion.exclusionIds));

            promotionRepository.search(criteria, Shopware.Context.api).then((excluded) => {
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
            return new EntityCollection('/promotion', 'promotion', Shopware.Context.api, new Criteria());
        }
    }
});
