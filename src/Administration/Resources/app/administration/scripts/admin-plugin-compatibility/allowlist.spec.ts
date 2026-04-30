/**
 * @sw-package framework
 */

import { splitRuntimeErrors } from './allowlist';

describe('admin-plugin-compatibility runtime allowlist', () => {
    it('separates known unsupported runtime errors from regressions', () => {
        expect(splitRuntimeErrors([
            'native block adapter limitation',
            'unexpected dynamic import failure',
        ], [
            { pattern: 'native block adapter', reason: 'Known adapter limitation' },
        ])).toEqual({
            knownUnsupported: [{
                error: 'native block adapter limitation',
                reason: 'Known adapter limitation',
            }],
            regressions: ['unexpected dynamic import failure'],
        });
    });
});
