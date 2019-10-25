const { Component } = Shopware;

Component.override('sw-category-detail', {
    template: '',

    inject: ['seoUrlService'],

    methods: {
        onSave() {
            // if extensions are not set the seo urls are not present in store
            if (typeof this.$store.getters['swSeoUrl/getNewOrModifiedUrls'] !== 'function') {
                this.$super('onSave');
                return;
            }

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
