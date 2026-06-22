/* eslint-disable @typescript-eslint/no-unsafe-member-access */

import { slotToItemsProp } from './slot-to-items-prop';
import { createAttribute, createFixer, createRuleApi, createSlot } from './test-utils';

describe('slotToItemsProp', () => {
    it('adds an items prop generated from slot children', () => {
        const usage = slotToItemsProp({
            prop: 'items',
            itemComponent: 'sw-tabs-item',
        });
        const slot = createSlot('default', [
            {
                type: 'VElement',
                name: 'sw-tabs-item',
                startTag: {
                    attributes: [createAttribute('name', { value: 'overview' })],
                },
                children: [
                    {
                        type: 'VText',
                        value: 'Overview',
                    },
                ],
            },
        ]);
        const node = {
            name: 'mt-tabs',
            startTag: {
                range: [
                    0,
                    9,
                ],
                attributes: [],
            },
            children: [slot],
        };
        const api = createRuleApi({ usage, node, slot });

        usage.eslint?.report(api);
        const fixes = api.reports[0].fix(createFixer());

        expect(fixes[0]).toEqual({
            method: 'insertTextAfterRange',
            range: [
                8,
                8,
            ],
            text: ` :items="[
    {
        'label': 'Overview',
        'name': 'overview'
    }
]"`,
        });
        expect(fixes[1].text).toContain('Please use the "items" property instead.');
    });
});
