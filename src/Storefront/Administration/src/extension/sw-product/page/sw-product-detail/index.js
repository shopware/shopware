const { Component } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.override('sw-product-detail', {
    template: '',

    inject: ['seoUrlService', 'apiContext'],

    methods: {
        onSaveFinished(response) {
            // if extensions are not set the seo urls are not present in store
            if (typeof this.$store.getters['swSeoUrl/getNewOrModifiedUrls'] !== 'function') {
                this.$super('onSaveFinished', response);
                return;
            }

            const seoUrls = this.$store.getters['swSeoUrl/getNewOrModifiedUrls']();
            const defaultSeoUrl = this.$store.state.swSeoUrl.defaultSeoUrl;

            const updatePromises = [];
            if (seoUrls) {
                seoUrls.forEach(seoUrl => {
                    if (!seoUrl.seoPathInfo) {
                        seoUrl.seoPathInfo = defaultSeoUrl.seoPathInfo;
                        seoUrl.isModified = false;
                    }

                    updatePromises.push(this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId));
                });
            }

            if (response === 'empty' && seoUrls.length > 0) {
                response = 'success';
            }

            Promise.all(updatePromises).then(() => {
                this.$super('onSaveFinished', response);

                this.$root.$emit('seo-url-save-finish');
            });

        }
    }
});
