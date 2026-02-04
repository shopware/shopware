/**
 * @sw-package framework
 */
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';
import { computed, unref } from 'vue';
import { useExtensionOrdereredArrayMap } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line max-len,sw-deprecation-rules/private-feature-declarations
export type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'> & {
    extensionName: string;
};

const ExtensionComponentSectionsStore = Shopware.Store.register('extensionComponentSections', () => {
    const sectionsByPosition = useExtensionOrdereredArrayMap<ComponentSectionEntry>();
    const identifier = sectionsByPosition.items;

    const addSection = ({
        component,
        positionId,
        src,
        props,
        extensionName,
    }: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string }) => {
        const positionArray = sectionsByPosition.get(positionId);
        positionArray.push({
            component,
            src,
            props,
            extensionName,
        });
    };

    return {
        identifier,
        addSection,
        clear: sectionsByPosition.clear,
        flushByCurrentExtension: sectionsByPosition.flushByCurrentExtension,
    };
});

/**
 * @private
 */
export type ExtensionComponentSectionsStore = ReturnType<typeof ExtensionComponentSectionsStore>;

/**
 * @private
 */
export default ExtensionComponentSectionsStore;
