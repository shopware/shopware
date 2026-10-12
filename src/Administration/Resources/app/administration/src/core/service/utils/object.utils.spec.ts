/**
 * @sw-package discovery
 */

import { getObjectDiff } from './object.utils';

describe('src/core/service/utils/object.utils', () => {
    describe('getObjectDiff', () => {
        const cases: [string, Record<string, unknown>, Record<string, unknown>, Record<string, unknown>][] = [
            [
                'returns an empty diff for unchanged values',
                { value: 'image-id' },
                { value: 'image-id' },
                {},
            ],
            [
                'reports changed scalar values',
                { value: 'first-image-id' },
                { value: 'second-image-id' },
                { value: 'second-image-id' },
            ],
            [
                'reports added properties',
                { value: 'image-id' },
                { value: 'image-id', source: 'static' },
                { source: 'static' },
            ],
            [
                'reports removed properties while retaining the remaining object',
                { value: { imageId: 'image-id', source: 'static' } },
                { value: { imageId: 'image-id' } },
                { value: { imageId: 'image-id' } },
            ],
            [
                'reports changed nested properties',
                { value: { image: { id: 'first-image-id' } } },
                { value: { image: { id: 'second-image-id' } } },
                { value: { image: { id: 'second-image-id' } } },
            ],
            [
                'reports an array cleared to empty as a change',
                { value: ['image-id'] },
                { value: [] },
                { value: [] },
            ],
            [
                'reports an object cleared to empty as a change',
                { value: { imageId: 'image-id' } },
                { value: {} },
                { value: {} },
            ],
        ];

        it.each(cases)('%s', (_description, currentValue, nextValue, expectedDiff) => {
            expect(getObjectDiff(currentValue, nextValue)).toEqual(expectedDiff);
        });
    });
});
