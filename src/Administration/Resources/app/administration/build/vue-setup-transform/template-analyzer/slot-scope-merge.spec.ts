/**
 * @sw-package framework
 */

import { mergeObjectSlotExpression } from './slot-scope-merge';

const PRIVATE_MAPPING = {
    sourceKey: '__swOverride',
    source: '__swOverride: { ns_abcde: { info } }',
};

describe('build/vue-setup-transform/template-analyzer/slot-scope-merge', () => {
    it('appends generated entries after plain existing bindings', () => {
        expect(
            mergeObjectSlotExpression('{ body }', [
                PRIVATE_MAPPING,
            ]),
        ).toBe('{ body, __swOverride: { ns_abcde: { info } } }');
    });

    it('inserts generated entries before the first default so defaults can read them', () => {
        expect(
            mergeObjectSlotExpression('{ body, info = fallbackInfo }', [
                PRIVATE_MAPPING,
            ]),
        ).toBe('{ body, __swOverride: { ns_abcde: { info } }, info = fallbackInfo }');
    });

    it('does not duplicate an entry the author already destructures', () => {
        expect(
            mergeObjectSlotExpression('{ body }', [
                {
                    sourceKey: 'body',
                    source: 'body',
                },
            ]),
        ).toBe('{ body }');
    });
});
