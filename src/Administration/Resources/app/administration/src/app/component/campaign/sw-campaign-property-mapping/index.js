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
                headline: bannerInfo.content?.headline?.[this.currentLocale]
                    ?? bannerInfo.content?.headline?.['en-GB'],
                mainAction: bannerInfo?.content?.mainAction,
                inlineActions: bannerInfo?.content?.description?.inlineActions,
                // optional properties
                textColor: bannerInfo?.content?.textColor,
                linkColor: bannerInfo?.content?.linkColor,
                bgColor: bannerInfo?.background?.color,
                bgImage: bannerInfo?.background?.image,
                bgPosition: bannerInfo?.background?.position,
                leftImage: bannerInfo?.leftImage?.src?.[this.currentLocale]
                    ?? bannerInfo?.leftImage?.src?.['en-GB'],
                leftImageSourceSet: bannerInfo?.leftImage?.srcset?.[this.currentLocale]
                    ?? bannerInfo?.leftImage?.srcset?.['en-GB'],
                leftImageBgColor: bannerInfo?.leftImage?.bgColor,
                labelText: bannerInfo?.content?.label?.text?.[this.currentLocale]
                    ?? bannerInfo?.content?.label?.text?.['en-GB'],
                labelTextColor: bannerInfo?.content?.label?.textColor,
                labelBgColor: bannerInfo?.content?.label?.bgColor,
                bannerIsClickable: bannerInfo.content?.mainAction?.bannerIsClickable,
                alwaysShowLeftImage: !bannerInfo?.leftImage?.hideInSmallViewports,
                componentName: this.componentName,
            };

            return propMapping;
        },
    },
});

