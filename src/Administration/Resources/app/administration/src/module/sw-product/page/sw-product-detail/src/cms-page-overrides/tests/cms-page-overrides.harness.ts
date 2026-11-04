/**
 * @sw-package inventory
 */

/** @private */
export { collectSlotOverrides, stripSlotConfigMetadata } from '../private';

/**
 * A page, or a changeset of one, with one section and one block holding the given slots.
 *
 * @private
 */
export function createPage(slots: Array<{ id: string; config: Record<string, Record<string, unknown>> | null }>) {
    return { sections: [{ blocks: [{ slots }] }] };
}
