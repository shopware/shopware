import { clickCloseButton, closeModal, getShownRawContent, mountEntryInfo, unmountEntryInfos } from './entry-info.harness';

/**
 * @sw-package framework
 */

/**
 * The log entry info is the modal the log list opens for one log entry.
 * It shows the context the entry was logged with, as formatted JSON in a raw tab, and the mail log modal extends it.
 * The log entry comes from the list as a prop, and the 6.8 feature flag picks between the meteor tabs and the older ones.
 */
describe('sw-settings-logging-entry-info', () => {
    afterEach(unmountEntryInfos);

    /**
     * @requirement A merchant sees the context of a log entry as readable JSON.
     * The context holds the details of what happened, and a merchant or support reads it to find a cause.
     */
    it('shows the context of the log entry as formatted JSON', async () => {
        // ACT
        const wrapper = await mountEntryInfo({ context: { order: { id: 'order-1' } } });

        // ASSERT
        expect(getShownRawContent(wrapper)).toBe('{\n  "order": {\n    "id": "order-1"\n  }\n}');
    });

    /**
     * @requirement A merchant sees the context in the 6.8 layout as well.
     * The major release replaces the old tabs, and the log entry must stay readable through it.
     */
    it('shows the context of the log entry in the 6.8 layout', async () => {
        // ACT
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } }, { majorFeature: true });

        // ASSERT
        expect(getShownRawContent(wrapper)).toBe('{\n  "foo": "bar"\n}');
    });

    /**
     * @requirement A merchant can close the log entry info with its close button.
     */
    it('closes with the close button', async () => {
        // ARRANGE
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } });

        // ACT
        await clickCloseButton(wrapper);

        // ASSERT
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    /**
     * @requirement A merchant can close the log entry info like any other modal.
     */
    it('closes with the modal itself', async () => {
        // ARRANGE
        const wrapper = await mountEntryInfo({ context: { foo: 'bar' } });

        // ACT
        await closeModal(wrapper);

        // ASSERT
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
