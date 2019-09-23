import { mapApiErrors } from 'src/app/service/map-errors.service';
import template from './sw-promotion-basic-form.html.twig';
import './sw-promotion-basic-form.scss';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const types = Shopware.Utils.types;

Component.register('sw-promotion-basic-form', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        }
    },

    data() {
        return {
            excludedPromotions: null
        };
    },

    computed: {
        ...mapApiErrors('promotion', ['name', 'validUntil']),
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.promotion.id)]));
            return criteria;
        }
    },

    watch: {
        promotion() {
            if (this.promotion) {
                this.loadExclusions();
            }
        }
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

            promotionRepository.search(criteria, this.context).then((excluded) => {
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
        },

        createPromotionCollection() {
            return new EntityCollection('/promotion', 'promotion', this.context, new Criteria());
        }
    }
});
