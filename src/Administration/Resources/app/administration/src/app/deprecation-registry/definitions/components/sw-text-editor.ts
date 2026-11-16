/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor',
    component: 'sw-text-editor',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    overrideHint:
        'sw-text-editor is removed. Use mt-text-editor: v-model:value → v-model, @update:value → @update:model-value, button-config → custom-buttons, allow-inline-data-mapping → createTextEditorDataMappingButton() from Shopware.Component.getComponentHelper() in custom-buttons. sanitize-*, vertical-align and enable-transparent-background have no replacement.',
    onConvert: [
        renameComponent({
            from: 'sw-text-editor',
            to: 'mt-text-editor',
            fix: 'manual',
            message:
                'Use mt-text-editor: v-model:value → v-model, @update:value → @update:model-value, button-config → custom-buttons, allow-inline-data-mapping → createTextEditorDataMappingButton() from Shopware.Component.getComponentHelper() in custom-buttons. sanitize-*, vertical-align and enable-transparent-background have no replacement.',
        }),
    ],
});
