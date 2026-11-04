import { collectSlotOverrides, stripSlotConfigMetadata } from '../private';

/**
 * @sw-package inventory
 */

/**
 * A page, or a changeset of one, with one section and one block holding the given slots.
 */
function createPage(slots: Array<{ id: string; config: Record<string, Record<string, unknown>> | null }>) {
    return { sections: [{ blocks: [{ slots }] }] };
}

// A product can override the content of the slots in its layout, and stores those overrides by slot id.
// The overrides are what the merchant changed on the layout page, without the fields the layout editor keeps for itself.
describe('the slot overrides of a product layout', () => {
    /**
     * @requirement A merchant's changes to the slots of a layout are stored with the product, by slot id.
     */
    it('collects the config of every changed slot', () => {
        // ARRANGE
        const changes = createPage([{ id: 'slot-1', config: { content: { value: 'Hello', source: 'static' } } }]);

        // ACT
        const overrides = collectSlotOverrides({}, () => changes);

        // ASSERT
        expect(overrides).toEqual({ 'slot-1': { content: { value: 'Hello', source: 'static' } } });
    });

    /**
     * @requirement A product without changes to its layout overrides nothing.
     */
    it('collects nothing from an unchanged page', () => {
        // ACT
        const overrides = collectSlotOverrides({}, () => null);

        // ASSERT
        expect(overrides).toEqual({});
    });

    /**
     * @requirement Only values the merchant set count as changes, not what the layout editor adds for itself.
     */
    it('strips the editor fields from a slot config', () => {
        // ARRANGE
        const page = createPage([
            {
                id: 'slot-1',
                config: {
                    media: { value: 'media-id', source: 'static', entity: { name: 'media' }, required: true, type: 'x' },
                },
            },
        ]);

        // ACT
        stripSlotConfigMetadata(page.sections);

        // ASSERT
        expect(page.sections[0].blocks[0].slots[0].config).toEqual({ media: { value: 'media-id', source: 'static' } });
    });
});
