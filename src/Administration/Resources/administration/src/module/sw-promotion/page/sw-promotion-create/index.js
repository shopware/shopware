import { Component, State } from 'src/core/shopware';
import template from './sw-promotion-create.html.twig';

Component.extend('sw-promotion-create', 'sw-promotion-detail', {
    template,

    inject: ['numberRangeService'],

    data() {
        return {
            promotionNumberPreview: ''
        };
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            this.promotion = this.promotionRepository.create(this.context);

            this.numberRangeService.reserve('promotion', '', true).then((response) => {
                this.promotionNumberPreview = response.number;
                this.promotion.promotionNumber = response.number;
            });
        },

        onSave() {
            if (this.promotionNumberPreview === this.promotion.promotionNumber) {
                this.numberRangeService.reserve('promotion').then((response) => {
                    this.promotionNumberPreview = 'reserved';
                    this.promotion.promotionNumber = response.number;
                    this.$super.onSave().then(() => {
                        this.$router.push({ name: 'sw.promotion.detail', params: { id: this.promotion.id } });
                    });
                });
            } else {
                this.$super.onSave().then(() => {
                    this.promotionNumberPreview = 'reserved';
                    this.$router.push({ name: 'sw.promotion.detail', params: { id: this.promotion.id } });
                });
            }
        }
    }
});
