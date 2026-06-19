/**
 * @sw-package framework
 */

import { computed } from 'vue';
import { useMeteorTableSlots } from './use-meteor-table-slots';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-slots', () => {
    it('normalizes Meteor and legacy slot scope aliases', () => {
        const slots = useMeteorTableSlots({
            slots: {},
            inlineEditableColumns: computed(() => []),
            isInlineEditing: () => false,
            openDetail: jest.fn(),
        });
        const record = {
            id: 'record-1',
            name: 'First',
        };
        const column = {
            property: 'name',
            label: 'Name',
            renderer: 'text' as const,
            position: 0,
        };

        expect(slots.normalizeForwardedSlotScope({ data: record, columnDefinition: column })).toEqual({
            data: record,
            item: record,
            columnDefinition: column,
            column,
        });
        expect(slots.normalizeLegacyPreviewSlotScope({ data: record }, column)).toEqual(
            expect.objectContaining({
                data: record,
                item: record,
                column,
                columnDefinition: column,
                compact: false,
            }),
        );
    });

    it('keeps inline editable column and legacy preview slots out of forwarded slots', () => {
        const slots = useMeteorTableSlots({
            slots: {
                'column-name': () => [],
                'preview-name': () => [],
                toolbar: () => [],
            },
            inlineEditableColumns: computed(() => [
                {
                    property: 'name',
                    label: 'Name',
                    renderer: 'text',
                    position: 0,
                    inlineEdit: 'string',
                },
            ]),
            isInlineEditing: () => false,
            openDetail: jest.fn(),
        });

        expect(slots.forwardedSlotNames.value).toEqual(['toolbar']);
    });
});
