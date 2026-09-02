import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { ComponentSectionEntry } from 'src/app/store/extension-component-sections.store';
import template from './sw-extension-component-section.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-extension-component-section positionId="my-special-position" />
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['feature'],

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
            const sections = this.sortSections(
                Shopware.Store.get('extensionComponentSections').identifier[this.positionIdentifier] ?? [],
            );
            if (sections.length && this.deprecated) {
                sections.forEach((section) => {
                    const debugArgs = [
                        'CORE',
                        `The extension "${section.extensionName}" uses a deprecated position identifier "${this.positionIdentifier}". ${this.deprecationMessage}`,
                    ];
                    // @ts-expect-error
                    if (process.env !== 'prod') {
                        // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- Deprecated extension positions do not expose a removal version or matching major feature flag.
                        Shopware.Utils.debug.error(...debugArgs);
                    } else {
                        // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- Deprecated extension positions do not expose a removal version or matching major feature flag.
                        Shopware.Utils.debug.warn(...debugArgs);
                    }
                });
            }

            return sections;
        },

        componentSectionTabItems(): TabItem[][] {
            return this.componentSections.map((componentSection) => {
                if (!componentSection.props || !('tabs' in componentSection.props)) {
                    return [];
                }

                return (
                    componentSection.props.tabs?.map((tab) => {
                        return {
                            label: this.$t(tab.label ?? ''),
                            name: tab.name,
                        };
                    }) ?? []
                );
            });
        },
    },

    data() {
        return {
            activeTabName: '',
        };
    },

    methods: {
        /**
         * Sorts the sections for this position identifier:
         * 1. Sections registered by services (`sourceType === 'service'`) always render above app sections.
         * 2. Within each group, ascending `priority` (1 = topmost). Entries without a valid `priority`
         *    render below those that set one (unset sorts last).
         * 3. Ties (equal `priority`, or both unset) keep their original registration order. The sort is
         *    stable, so returning `0` preserves the array index — no extension is favoured by name.
         */
        sortSections(sections: ComponentSectionEntry[]): ComponentSectionEntry[] {
            const extensionsState = Shopware.Store.get('extensions').extensionsState;

            const isService = (entry: ComponentSectionEntry): boolean =>
                extensionsState[entry.extensionName]?.sourceType === 'service';

            // Unset priorities sort last so explicitly prioritized entries always win their group.
            const priorityOf = (entry: ComponentSectionEntry): number => entry.priority ?? Number.MAX_SAFE_INTEGER;

            return [...sections].sort((a, b) => {
                const serviceDiff = Number(isService(b)) - Number(isService(a));
                if (serviceDiff !== 0) {
                    return serviceDiff;
                }

                return priorityOf(a) - priorityOf(b);
            });
        },

        setActiveTab(name: string) {
            this.activeTabName = name;
        },

        getActiveTab(componentSection: ComponentSectionEntry) {
            if ('tabs' in componentSection.props) {
                return this.activeTabName
                    ? componentSection.props.tabs?.find((tab) => tab.name === this.activeTabName)
                    : componentSection.props.tabs?.[0];
            }

            return null;
        },
    },
});
