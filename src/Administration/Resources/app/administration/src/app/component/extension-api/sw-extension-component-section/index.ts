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

    extensionApiDevtoolInformation: {
        property: 'ui.componentSection',
        method: 'add',
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        positionId: (currentComponent) => currentComponent.positionIdentifier as string,
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
                ? componentSection.props.tabs.find(tab => tab.name === this.activeTabName)
                : componentSection.props.tabs[0];
        },
    },

    props: {
        positionIdentifier: {
            type: String,
            required: true,
        },
    },

    computed: {
        componentSections(): ComponentSectionEntry[] {
            return Shopware.State.get('extensionComponentSections').identifier[this.positionIdentifier] ?? [];
        },
    },
});
