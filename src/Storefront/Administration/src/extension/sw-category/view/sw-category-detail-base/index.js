import template from './sw-category-detail-base.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-category-detail-base', {
    template,

    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            seoUrls: []
        };
    },
    computed: {
        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        }
    },

    methods: {
        initSeoUrls() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('seo_url.isCanonical', true));

            this.seoUrlRepository.search(criteria, this.context).then((result) => {
                this.seoUrls = result.item;
            });
        }
    }
});
