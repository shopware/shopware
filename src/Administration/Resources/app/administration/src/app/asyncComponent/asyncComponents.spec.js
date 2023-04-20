/**
 * @package admin
 */

import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

describe('src/app/asyncComponent/asyncComponent', () => {
    it('should register the components asynchronously', async () => {
        expect(Shopware.Component.getComponentRegistry().size).toBe(0);

        await registerAsyncComponents();

        expect(Shopware.Component.getComponentRegistry().has('sw-code-editor')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-chart')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-datepicker')).toBe(true);

        expect(Shopware.Component.getComponentRegistry().has('sw-image-slider')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-add-thumbnail-form')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-base-item')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-compact-upload-v2')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-entity-mapper')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-field')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-folder-content')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-folder-item')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-list-selection-item-v2')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-list-selection-v2')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-media-item')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-modal-delete')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-modal-folder-dissolve')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-modal-folder-settings')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-modal-move')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-modal-replace')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-preview-v2')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-replace')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-upload-v2')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-media-url-form')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-sidebar-media-item')).toBe(true);
        expect(Shopware.Component.getComponentRegistry().has('sw-ai-copilot-badge')).toBe(true);
    });
});
