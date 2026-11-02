import { buildJestArguments } from './arguments';

describe('buildJestArguments', () => {
    it('runs the tests of one module with coverage of its src/ only', () => {
        // ACT
        const jestArguments = buildJestArguments('src/core/order/primary-delivery/', false);

        // ASSERT
        expect(jestArguments).toEqual([
            '--testPathPatterns',
            'src/core/order/primary-delivery/tests/',
            '--coverage',
            '--collectCoverageFrom',
            'src/core/order/primary-delivery/src/**/*.{js,ts}',
            '--coverageReporters',
            'text',
        ]);
    });

    it('runs only the contract specs of one module', () => {
        // ACT
        const jestArguments = buildJestArguments('src/core/order/primary-delivery', true);

        // ASSERT
        expect(jestArguments[1]).toBe(
            'src/core/order/primary-delivery/tests/(requirements|pinned|regressions)\\.spec\\.[jt]s$',
        );
    });

    it('runs the contract specs of every module without coverage', () => {
        // ACT
        const jestArguments = buildJestArguments(null, true);

        // ASSERT
        expect(jestArguments).toEqual([
            '--testPathPatterns',
            '/tests/(requirements|pinned|regressions)\\.spec\\.[jt]s$',
            '--coverage=false',
        ]);
    });
});
