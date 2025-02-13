// eslint-disable-next-line max-len
import type { CustomButton } from '@shopware-ag/meteor-component-library/dist/esm/components/form/mt-text-editor/_internal/mt-text-editor-toolbar';

/**
 * @sw-package framework
 *
 * @private
 */
export default (getAvailableDataMappings: () => string[]): CustomButton => {
    const dataMappings = getAvailableDataMappings();

    return {
        icon: 'regular-variables-xs',
        name: 'cms-data-mapping',
        position: 14000,
        // @ts-expect-error
        label: Shopware.Snippet.t('sw-text-editor-toolbar-button-cms-data-mapping.label') as string,
        children: dataMappings.map((dataMapping) => ({
            name: dataMapping,
            label: dataMapping,
            action(editor) {
                return editor.commands.insertContent(`{{ ${dataMapping} }}`);
            },
        })),
    };
};
