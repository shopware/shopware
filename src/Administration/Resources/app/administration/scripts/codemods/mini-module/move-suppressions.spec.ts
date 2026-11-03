import { moveSuppressionKeys } from './move-suppressions';

describe('moveSuppressionKeys', () => {
    it('moves the keys of files inside a moved spec directory and drops the keys whose file is gone', () => {
        // ARRANGE
        const suppressions = {
            'src/x/index.js': { 'max-len': { count: 1 } },
            'src/x/sw-x.spec/a.spec.js': { 'sw-test-rules/no-vm-access': { count: 3 } },
            'src/x/deleted.spec.js': { 'sw-test-rules/no-vm-access': { count: 1 } },
            'src/other/index.js': { 'max-len': { count: 1 } },
        };
        const moves = new Map([
            ['src/x/index.js', 'src/x/src/sw-x.js'],
            ['src/x/sw-x.spec', 'src/x/tests/sw-x.spec'],
        ]);
        const files = new Set(['src/x/src/sw-x.js', 'src/x/tests/sw-x.spec/a.spec.js']);

        // ACT
        const result = moveSuppressionKeys(suppressions, moves, 'src/x', (file) => files.has(file));

        // ASSERT
        expect(result).toEqual({
            'src/other/index.js': { 'max-len': { count: 1 } },
            'src/x/src/sw-x.js': { 'max-len': { count: 1 } },
            'src/x/tests/sw-x.spec/a.spec.js': { 'sw-test-rules/no-vm-access': { count: 3 } },
        });
        expect(Object.keys(result)).toEqual([
            'src/other/index.js',
            'src/x/src/sw-x.js',
            'src/x/tests/sw-x.spec/a.spec.js',
        ]);
    });
});
