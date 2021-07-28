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
            const {
                component,
                campaignName,
            } = Shopware.State.getters['marketing/getActiveCampaignDataForComponent'](this.componentName);

            if (!component) {
                return null;
            }

            if (this.componentName === 'storeBanner') {
                return {
                    campaignName: campaignName,
                    headline: this.getTranslatedProp(component.content?.headline),
                    text: this.getTranslatedProp(component.content?.description),
                    bgImage: component?.background?.image,
                    bgColor: component?.background?.color,
                    bgPosition: component?.background?.position,
                    textColor: component?.content?.textColor,
                    textAction: this.getTranslatedProp(component?.content?.cta?.text),
                    toBeOpenedCategory: component?.content?.cta?.category,
                };
            }

            return {
                // required properties
                campaignName: campaignName,
                headline: this.getTranslatedProp(component.content?.headline),
                mainAction: component?.content?.mainAction,
                inlineActions: component?.content?.description?.inlineActions,
                leftImage: this.getTranslatedProp(component?.leftImage?.src),
                componentName: this.componentName,
                // optional properties
                textColor: component?.content?.textColor,
                linkColor: component?.content?.linkColor,
                bgColor: component?.background?.color,
                bgImage: component?.background?.image,
                bgPosition: component?.background?.position,
                leftImageSourceSet: this.getTranslatedProp(component?.leftImage?.srcset),
                leftImageBgColor: component?.leftImage?.bgColor,
                labelText: this.getTranslatedProp(component?.content?.label?.text),
                labelTextColor: component?.content?.label?.textColor,
                labelBgColor: component?.content?.label?.bgColor,
                bannerIsClickable: component.content?.mainAction?.bannerIsClickable,
                alwaysShowLeftImage: !component?.leftImage?.hideInSmallViewports,
            };
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

