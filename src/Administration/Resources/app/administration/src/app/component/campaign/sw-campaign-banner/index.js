import template from './sw-campaign-banner.html.twig';
import './sw-campaign-banner.scss';

/**
 * @private
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-campaign-banner
 *     campaignName="Your campaign name"
 *     componentName="dashboardBanner"
 *     headline="Awesome offer"
 *     :inlineActions="[
 *                {
 *                    placeholder: 'goToExtensionStore',
 *                    text: {
 *                        'de-DE': 'string',
 *                        'en-GB': 'string'
 *                    },
 *                    route: 'sw.extension.store.index.extensions',
 *                },
 *                {
 *                   placeholder: 'goToExtensionStore',
 *                   text: {
 *                       'de-DE': 'string',
 *                       'en-GB': 'string'
 *                   },
 *                   execution: {
 *                       arguments: ['category', 'summerSale2021'],
 *                       method: 'linkToExtensionStoreAndSelectCategory',
 *                   },
 *               },
 *               {
 *                   placeholder: 'goToExtensionStore',
 *                   text: {
 *                       'de-DE': 'string',
 *                       'en-GB': 'string'
 *                   },
 *                   externalLink: {
 *                       'de-DE': 'https://www.shopware.de',
 *                       'en-GB': 'https://www.shopware.com',
 *                   },
 *               }
 *          ]"
 *     :mainAction="{
 *           // Possible variants:
 *           //    - 'primary',
 *           //    - 'ghost',
 *           //    - 'contrast',
 *           //    - 'context',
 *           //    - 'default',
 *           buttonVariant: 'ghost',
 *           cta: {
 *               'de-DE': 'string (max 20)',
 *               'en-GB': 'string (max 20)'
 *           },
 *           // only one of these properties is available
 *           execution: {
 *               arguments: ['category', 'summerSale2021'],
 *               method: 'linkToExtensionStoreAndSelectCategory',
 *           }
 *           // or
 *           route: 'sw.extension.store.index.extensions',
 *           // or
 *           externalLink: {
 *               'de-DE': 'https://www.shopware.de',
 *               'en-GB': 'https://www.shopware.com',
 *           }
 *     }"
 *     leftImage="http://www.your-left.image/test.jpg"
 *     >
 * <sw-campaign-banner>
 */
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
            type: Array,
            required: false,
            default: () => [],
        },
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
        // whether the leftImage should be shown in smaller viewports
        alwaysShowLeftImage: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        bannerIsClickable() {
            return this.mainAction?.bannerIsClickable;
        },

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
                'sw-campaign-banner__image--hidden-responsive': !this.alwaysShowLeftImage,
            };
        },

        actionComponent() {
            const actionVariant = this.mainAction?.buttonVariant ?? 'default';

            return {
                name: 'sw-button',
                text: this.getTranslatedProp(this.mainAction?.cta),
                props: {
                    variant: actionVariant !== 'default' ? actionVariant : undefined,
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
                    text: this.getTranslatedProp(inlineAction.text),
                };
            });
        },
    },

    methods: {
        handleBannerClick() {
            if (!this.bannerIsClickable) {
                return;
            }

            // get handler for mainAction
            const action = this.getActionHandler(this.mainAction);

            // execute main action
            action?.();
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
            const externalLink = this.getTranslatedProp(externalLinks);

            // open external link
            window.open(externalLink);
        },

        executionAction(execution) {
            try {
                // execute the defined method in "method" with the given "arguments"
                if (Array.isArray(execution?.arguments)) {
                    this[execution.method](...execution.arguments);
                    return;
                }

                this[execution.method]();
            } catch (error) {
                Shopware.Utils.debug.error(error);
            }
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

        showBookingOptions() {
            this.externalLinkAction({
                'en-GB': 'https://store.shopware.com/en/licenses',
                'de-DE': 'https://store.shopware.com/lizenzen',
            });
        },

        selectBookingOption(filterProperty, filterValue) {
            if (typeof filterValue === 'string') {
                filterValue = Number.parseInt(filterValue, 10);
            }

            if (Number.isNaN(filterValue)) {
                return;
            }

            // go to extension detail page
            this.$router.push({
                name: 'sw.extension.store.detail',
                params: { [filterProperty]: String(filterValue) },
            });
        },

        getTranslatedProp(translations) {
            if (!translations) {
                return undefined;
            }

            return translations[this.currentLocale] ?? translations['en-GB'];
        },
    },
});
