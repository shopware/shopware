import template from './sw-campaign-banner.html.twig';
import './sw-campaign-banner.scss';

Shopware.Component.register('sw-campaign-banner', {
    template,

    props: {
        campaignName: {
            type: String,
            required: true,
        },

        componentName: {
            type: String,
            required: true,
        },

        headline: {
            type: String,
            required: true,
        },

        inlineActions: {
            /*
            Data structure:

                [
                    {
                        "placeholder": "goToExtensionStore",
                        "text": {
                            "de-DE": "string",
                            "en-GB": "string"
                        },
                        "route": "sw.extension.store.index.extensions",
                    },
                    {
                        "placeholder": "goToExtensionStore",
                        "text": {
                            "de-DE": "string",
                            "en-GB": "string"
                        },
                        "execution": {
                            "arguments": ['category', 'summerSale2021'],
                            "method": 'linkToExtensionStoreAndSelectCategory',
                        },
                    },
                    {
                        "placeholder": "goToExtensionStore",
                        "text": {
                            "de-DE": "string",
                            "en-GB": "string"
                        },
                        "externalLink": {
                            'de-DE': 'https://www.shopware.de',
                            'en-GB': 'https://www.shopware.com',
                        },
                    },
                    ...
                ]

            */
            type: Array,
            required: false,
            default: () => [],
        },
        /**
         {
            Possible variants:
                - 'buttonVariantPrimary',
                - 'buttonVariantGhost',
                - 'buttonVariantContrast',
                - 'buttonVariantContext',
                - 'buttonVariantDefault',
                - 'internalLink',
                - 'externalLink',

            "variant":"internalLink",
            "bannerIsClickable":false,
            "cta": {
                    "de-DE":"string (max 20)",
                    "en-GB":"string (max 20)"
            },

            // last property can be "execution", "route" or "externalLink"

            "execution": {
                "arguments": ['category', 'summerSale2021'],
                "method": 'linkToExtensionStoreAndSelectCategory',
            }

            // or

            {
                "route": "sw.extension.store.index.extensions",
            }

            // or

            "externalLink": {
                'de-DE': 'https://www.shopware.de',
                'en-GB': 'https://www.shopware.com',
            }
        }
         */
        mainAction: {
            type: Object,
            required: true,
        },

        textColor: {
            type: String,
            required: false,
            default: '#52667a',
        },

        linkColor: {
            type: String,
            required: false,
            default: '#189eff',
        },
        // background
        bgColor: {
            type: String,
            required: false,
            default: '#fff',
        },
        bgImage: {
            type: String,
            required: false,
            default: null,
        },

        bgPosition: {
            type: String,
            required: false,
            default: '50% 50%',
        },
        // small image placed on the left
        leftImage: {
            type: String,
            required: true,
        },
        leftImageSourceSet: {
            type: String,
            required: false,
            default: null,
        },
        leftImageBgColor: {
            type: String,
            required: false,
            default: null,
        },
        // label
        labelText: {
            type: String,
            required: false,
            default: null,
        },
        labelTextColor: {
            type: String,
            required: false,
            default: null,
        },
        labelBgColor: {
            type: String,
            required: false,
            default: null,
        },
        // settings
        // whether to save if a user clicked the campaign
        saveClick: {
            type: Boolean,
            required: false,
            default: false,
        },
        // action should be executed if banner was clicked
        bannerIsClickable: {
            type: Boolean,
            required: false,
            default: false,
        },
        // whether the leftImage should be shown in smaller viewports
        alwaysShowLeftImage: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        containerStyles() {
            const cursorValue = this.bannerIsClickable ? 'pointer' : 'default';

            if (this.bgImage) {
                return {
                    cursor: cursorValue,
                    backgroundImage: `url(${this.bgImage})`,
                    backgroundPosition: this.bgPosition,
                    backgroundRepeat: 'no-repeat',
                    backgroundSize: 'cover',
                };
            }

            return {
                cursor: cursorValue,
                backgroundColor: this.bgColor,
            };
        },

        labelStyles() {
            return {
                background: this.labelBgColor,
                color: this.labelTextColor,
            };
        },

        imageClasses() {
            return {
                'is--hidden-responsive': !this.alwaysShowLeftImage,
            };
        },

        actionComponent() {
            const actionVariant = this.mainAction?.variant;

            const isButtonVariant = actionVariant.startsWith('buttonVariant');
            let variant = actionVariant.replace('buttonVariant', '').toLowerCase();

            // remove variant when it should use the default variant
            if (variant === 'default') {
                variant = undefined;
            }

            return {
                name: 'sw-button',
                text: this.mainAction?.cta?.['en-GB'], // TODO: add translation
                props: {
                    variant: isButtonVariant ? variant : undefined,
                },
                handlers: {
                    click: this.getActionHandler(this.mainAction),
                },
            };
        },

        currentLocale() {
            return Shopware.State.get('session').currentLocale;
        },

        inlineActionsSlots() {
            return this.inlineActions.map((inlineAction) => {
                return {
                    placeholder: inlineAction.placeholder,
                    clickHandler: this.getActionHandler(inlineAction),
                    // TODO: add translation
                    text: inlineAction.text['en-GB'],
                };
            });
        },
    },

    methods: {
        emitExecuteAction() {
            if (this.saveClick) {
                localStorage.setItem('hasClickedCampaign', this.campaignName);
            }

            this.$emit('execute-action');
        },

        handleBannerClick() {
            if (!this.bannerIsClickable) {
                return;
            }

            this.emitExecuteAction();
        },

        getActionHandler(action) {
            if (action.route) {
                return () => this.routeAction(action.route);
            }

            if (action.externalLink) {
                return () => this.externalLinkAction(action.externalLink);
            }

            if (action.execution) {
                return () => this.executionAction(action.execution);
            }

            return () => console.log('No matching action found');
        },

        routeAction(routeName) {
            this.$router.push({ name: routeName });
        },

        externalLinkAction(externalLinks) {
            // get link for active language
            const externalLink = externalLinks?.[this.currentLocale] ?? externalLinks?.['en-GB'];

            // open external link
            window.open(externalLink);
        },

        executionAction(execution) {
            // execute the defined method in "method" with the given "arguments"
            this[execution.method](...execution.arguments);
        },

        linkToExtensionStoreAndSelectCategory(filterProperty, filterValue) {
            // set filter value
            Shopware.State.get('shopwareExtensions').search.filter = {
                [filterProperty]: filterValue,
            };

            // go to route
            this.$router.push({
                name: 'sw.extension.store.listing.app',
            });
        },
    },
});
