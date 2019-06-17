import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';

Component.override('sw-product-detail', {
    template: '',

    inject: ['seoUrlService', 'context'],

    computed: {
        seoUrlCriteria() {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(
                Criteria.equals('isCanonical', true)
            );
            return criteria;
        }
    },

    methods: {
        loadProduct() {
            if (!this.productCriteria.hasAssociation('seoUrls')) {
                this.productCriteria.addAssociation('seoUrls', this.seoUrlCriteria);
            }

            this.$super.loadProduct();
        },

        onSaveFinished(response) {
            const seoUrls = this.$store.getters['swSeoUrl/getNewOrModifiedUrls']();

            seoUrls.forEach(seoUrl => {
                if (seoUrl.seoPathInfo) {
                    this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                }
            });

            if (response === 'empty' && seoUrls.length > 0) {
                response = 'success';
            }



            this.$super.onSaveFinished(response);
        }
    }
});
