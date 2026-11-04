import { ChangesetGenerator } from '../dependencies';

/**
 * @sw-package inventory
 */

type SlotConfig = Record<string, Record<string, unknown>>;

type Section = { blocks?: Array<{ slots?: Array<{ id?: string; config?: SlotConfig | null }> }> };

type ChangedPage = { sections?: unknown } | null;

/**
 * Turns a CMS page into its changeset. The default compares the page with its origin.
 *
 * @private
 */
type GenerateChanges = (page: unknown) => ChangedPage;

const generateWithChangesetGenerator: GenerateChanges = (page) =>
    new ChangesetGenerator().generate(page as Entity<'cms_page'>).changes as ChangedPage;

/**
 * Removes what the layout editor adds to a slot config field for itself (`entity`, `required`, `type`),
 * so only the values the merchant set count as changes. Changes the sections in place.
 *
 * @example
 * stripSlotConfigMetadata(page.sections);
 *
 * @private
 */
export function stripSlotConfigMetadata(sections: Section[] | null | undefined): void {
    const slots = (sections ?? []).flatMap((section) => section.blocks ?? []).flatMap((block) => block.slots ?? []);

    slots.forEach((slot) => {
        Object.values(slot.config ?? {}).forEach((configField) => {
            if (configField.entity) {
                delete configField.entity;
            }
            if (Object.prototype.hasOwnProperty.call(configField, 'required')) {
                delete configField.required;
            }
            if (configField.type) {
                delete configField.type;
            }
        });
    });
}

/**
 * The config of every slot the merchant changed on the page, by slot id.
 *
 * @example
 * collectSlotOverrides(cmsPage); // → { 'slot-id': { content: { value: 'Text', source: 'static' } } }
 *
 * @private
 */
export function collectSlotOverrides(
    page: unknown,
    generateChanges: GenerateChanges = generateWithChangesetGenerator,
): Record<string, unknown> {
    const changes = generateChanges(page);
    const slotOverrides: Record<string, unknown> = {};

    if (changes === null || !Array.isArray(changes.sections)) {
        return slotOverrides;
    }

    (changes.sections as Section[])
        .flatMap((section) => (Array.isArray(section.blocks) ? section.blocks : []))
        .flatMap((block) => (Array.isArray(block.slots) ? block.slots : []))
        .forEach((slot) => {
            slotOverrides[slot.id as string] = slot.config;
        });

    return slotOverrides;
}
