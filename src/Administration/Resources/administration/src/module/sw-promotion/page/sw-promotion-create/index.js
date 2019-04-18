import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-promotion-create.html.twig';

Component.extend('sw-promotion-create', 'sw-promotion-detail', {
    template,

    inject: ['numberRangeService'],

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.promotion.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    data() {
        return {
            productNumberPreview: ''
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

            if (this.$route.params.id) {
                this.promotionStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();

            this.numberRangeService.reserve('promotion', '', true).then((response) => {
                this.promotionNumberPreview = response.number;
                this.promotion.promotionNumber = response.number;

                this.promotion.isLoading = false;
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
