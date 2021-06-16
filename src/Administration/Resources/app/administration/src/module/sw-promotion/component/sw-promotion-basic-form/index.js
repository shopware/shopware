import template from './sw-promotion-basic-form.html.twig';
import './sw-promotion-basic-form.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria, EntityCollection } = Shopware.Data;
const types = Shopware.Utils.types;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-basic-form', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            excludedPromotions: null,
        };
    },

    computed: {
        ...mapPropertyErrors('promotion', ['name', 'validUntil']),
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.promotion.id)]));
            return criteria;
        },
    },

    watch: {
        promotion() {
            if (this.promotion) {
                this.loadExclusions();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        loadExclusions() {
            if (types.isEmpty(this.promotion.exclusionIds)) {
                this.excludedPromotions = this.createPromotionCollection();
                return;
            }

            const promotionRepository = this.repositoryFactory.create('promotion');
            const criteria = (new Criteria()).addFilter(Criteria.equalsAny('id', this.promotion.exclusionIds));

            promotionRepository.search(criteria).then((excluded) => {
                this.excludedPromotions = excluded;
            });
        },

        createdComponent() {
            if (this.promotion) {
                this.loadExclusions();
            }
        },

        onChangeExclusions(promotions) {
            this.promotion.exclusionIds = [];

            promotions.forEach((promotion) => {
                this.promotion.exclusionIds.push(promotion.id);
            });

            // reload the assigned entities
            // to show it in the admin
            this.loadExclusions();
        },

        createPromotionCollection() {
            return new EntityCollection('/promotion', 'promotion', Shopware.Context.api, new Criteria());
        },
    },
});
