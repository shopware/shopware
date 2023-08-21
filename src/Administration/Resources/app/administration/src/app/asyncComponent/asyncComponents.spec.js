/**
 * @package admin
 */

import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

const componentNames = [
    'sw-code-editor',
    'sw-chart',
    'sw-datepicker',
    'sw-image-slider',
    'sw-media-add-thumbnail-form',
    'sw-media-base-item',
    'sw-media-compact-upload-v2',
    'sw-media-entity-mapper',
    'sw-media-field',
    'sw-media-folder-content',
    'sw-media-folder-item',
    'sw-media-list-selection-item-v2',
    'sw-media-list-selection-v2',
    'sw-media-media-item',
    'sw-media-modal-delete',
    'sw-media-modal-folder-dissolve',
    'sw-media-modal-folder-settings',
    'sw-media-modal-move',
    'sw-media-modal-replace',
    'sw-media-preview-v2',
    'sw-media-replace',
    'sw-media-upload-v2',
    'sw-media-url-form',
    'sw-sidebar-media-item',
    'sw-ai-copilot-badge',
    'sw-ai-copilot-warning',
];

describe('src/app/asyncComponent/asyncComponent', () => {
    beforeAll(async () => {
        await registerAsyncComponents();
    });

    it.each(componentNames)('should register the %s synchronously', (componentName) => {
        expect(Shopware.Component.getComponentRegistry().has(componentName)).toBe(true);
    });

    it.each(componentNames)('should be able to build %s correctly', async (componentName) => {
        const buildResult = await Shopware.Component.build(componentName);
        // If component could not get build then the component library returns "false"
        expect(buildResult).not.toBe(false);
    });
});
