import type { ComponentSectionEntry } from 'src/app/state/extension-component-sections.store';
import template from './sw-extension-component-section.html.twig';

/**
 * @package admin
 *
 * @private
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-extension-component-section positionId="my-special-position" />
 */
Shopware.Component.register('sw-extension-component-section', {
    template,

    compatConfig: Shopware.compatConfig,

    extensionApiDevtoolInformation: {
        property: 'ui.componentSection',
        method: 'add',
        positionId: (currentComponent) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return currentComponent.positionIdentifier as string;
        },
    },

    props: {
        positionIdentifier: {
            type: String,
            required: true,
        },

        /**
         * Will mark the component section as deprecated, causing a warning in production and error in dev environments.
         */
        deprecated: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * Use this if you need to add additional information to the standard deprecation message.
         * @example "Use position identifier XYZ instead."
         */
        deprecationMessage: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        componentSections(): ComponentSectionEntry[] {
            const sections = Shopware.State.get('extensionComponentSections').identifier[this.positionIdentifier] ?? [];
            if (sections.length && this.deprecated) {
                sections.forEach((section) => {
                    const debugArgs = [
                        'CORE',
                        // eslint-disable-next-line max-len
                        `The extension "${section.extensionName}" uses a deprecated position identifier "${this.positionIdentifier}". ${this.deprecationMessage}`,
                    ];
                    // @ts-expect-error
                    if (process.env !== 'prod') {
                        Shopware.Utils.debug.error(...debugArgs);
                    } else {
                        // eslint-disable-next-line max-len
                        Shopware.Utils.debug.warn(...debugArgs);
                    }
                });
            }

            return sections;
        },
    },

    data() {
        return {
            activeTabName: '',
        };
    },

    methods: {
        setActiveTab(name: string) {
            this.activeTabName = name;
        },

        getActiveTab(componentSection: ComponentSectionEntry) {
            return this.activeTabName
                ? componentSection.props.tabs?.find((tab) => tab.name === this.activeTabName)
                : componentSection.props.tabs?.[0];
        },
    },
});
