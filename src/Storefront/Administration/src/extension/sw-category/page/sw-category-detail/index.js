const { Component } = Shopware;

Component.override('sw-category-detail', {
    template: '',

    inject: ['seoUrlService'],

    methods: {
        onSave() {
            this.$super.onSave();

            if (!this.next741) {
                return;
            }

            const seoUrls = this.$store.getters['swSeoUrl/getNewOrModifiedUrls']();

            seoUrls.forEach(seoUrl => {
                if (seoUrl.seoPathInfo) {
                    this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                }
            });
        }
    }
});
