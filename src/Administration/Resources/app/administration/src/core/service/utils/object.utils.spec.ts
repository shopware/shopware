/**
 * @sw-package discovery
 */

import { getObjectDiff } from './object.utils';

describe('src/core/service/utils/object.utils', () => {
    describe('getObjectDiff', () => {
        it('returns an empty diff for unchanged values', () => {
            expect(getObjectDiff({ value: 'image-id' }, { value: 'image-id' })).toEqual({});
        });

        it('reports changed scalar values', () => {
            expect(getObjectDiff({ value: 'first-image-id' }, { value: 'second-image-id' })).toEqual({
                value: 'second-image-id',
            });
        });

        it('reports added properties', () => {
            expect(getObjectDiff({ value: 'image-id' }, { value: 'image-id', source: 'static' })).toEqual({
                source: 'static',
            });
        });

        it('reports removed properties while retaining the remaining object', () => {
            expect(
                getObjectDiff({ value: { imageId: 'image-id', source: 'static' } }, { value: { imageId: 'image-id' } }),
            ).toEqual({ value: { imageId: 'image-id' } });
        });

        it('reports changed nested properties', () => {
            expect(
                getObjectDiff(
                    { value: { image: { id: 'first-image-id' } } },
                    { value: { image: { id: 'second-image-id' } } },
                ),
            ).toEqual({ value: { image: { id: 'second-image-id' } } });
        });

        it('reports an array cleared to empty as a change', () => {
            expect(getObjectDiff({ value: ['image-id'] }, { value: [] })).toEqual({ value: [] });
        });

        it('reports an object cleared to empty as a change', () => {
            expect(getObjectDiff({ value: { imageId: 'image-id' } }, { value: {} })).toEqual({ value: {} });
        });
    });
});
