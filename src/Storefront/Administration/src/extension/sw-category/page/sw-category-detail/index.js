const { Component } = Shopware;

Component.override('sw-category-detail', {
    template: '',

    inject: ['seoUrlService'],

    methods: {
        onSave() {
            this.$super('onSave');

            const seoUrls = this.$store.getters['swSeoUrl/getNewOrModifiedUrls']();

            seoUrls.forEach(seoUrl => {
                if (seoUrl.seoPathInfo) {
                    this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                }
            });
        }
    }
});
