Shopware.Component.register('sw-campaign-property-mapping', {

    render() {
        return this.$scopedSlots.default({
            mappedProperties: this.mappedProperties,
        });
    },

    props: {
        componentName: {
            type: String,
            required: true,
        },
    },

    computed: {
        currentLocale() {
            return Shopware.State.get('session').currentLocale;
        },

        mappedProperties() {
            const bannerInfo = Shopware.State.getters['marketing/getActiveCampaignDataForComponent'](this.componentName);

            if (!bannerInfo) {
                return null;
            }

            const propMapping = {
                // required properties
                campaignName: '', // campaign.name
                headline: this.getTranslatedProp(bannerInfo.content?.headline),
                mainAction: bannerInfo?.content?.mainAction,
                inlineActions: bannerInfo?.content?.description?.inlineActions,
                // optional properties
                textColor: bannerInfo?.content?.textColor,
                linkColor: bannerInfo?.content?.linkColor,
                bgColor: bannerInfo?.background?.color,
                bgImage: bannerInfo?.background?.image,
                bgPosition: bannerInfo?.background?.position,
                leftImage: this.getTranslatedProp(bannerInfo?.leftImage?.src),
                leftImageSourceSet: this.getTranslatedProp(bannerInfo?.leftImage?.srcset),
                leftImageBgColor: bannerInfo?.leftImage?.bgColor,
                labelText: this.getTranslatedProp(bannerInfo?.content?.label?.text),
                labelTextColor: bannerInfo?.content?.label?.textColor,
                labelBgColor: bannerInfo?.content?.label?.bgColor,
                bannerIsClickable: bannerInfo.content?.mainAction?.bannerIsClickable,
                alwaysShowLeftImage: !bannerInfo?.leftImage?.hideInSmallViewports,
                componentName: this.componentName,
            };

            return propMapping;
        },
    },

    methods: {
        getTranslatedProp(translations) {
            if (!translations) {
                return undefined;
            }

            return translations[this.currentLocale] ?? translations['en-GB'];
        },
    },
});

