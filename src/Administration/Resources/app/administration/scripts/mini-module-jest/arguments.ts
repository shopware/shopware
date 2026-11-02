/**
 * @sw-package framework
 * @private
 */

/** Spec files that test the public API of a mini-module, as opposed to its workshop specs. */
const CONTRACT_SPECS = '(requirements|pinned|regressions)\\.spec\\.[jt]s$';

/**
 * Builds the Jest arguments for a run scoped to mini-modules.
 * With a module directory, the run takes that module's tests and measures coverage of its `src/` only,
 * so the report shows which implementation code its tests reach.
 * With `contractsOnly`, the run takes only the requirements, pinned and regressions specs:
 * the coverage then shows which code no contract protects.
 *
 * @example
 * buildJestArguments('src/core/order/primary-delivery', true);
 * // → ['--testPathPatterns', 'src/core/order/primary-delivery/tests/(requirements|pinned|regressions)\\.spec\\.[jt]s$',
 * //    '--coverage', '--collectCoverageFrom', 'src/core/order/primary-delivery/src/**\/*.{js,ts}', '--coverageReporters', 'text']
 * buildJestArguments(null, true);
 * // → ['--testPathPatterns', '/tests/(requirements|pinned|regressions)\\.spec\\.[jt]s$', '--coverage=false']
 */
export function buildJestArguments(moduleDirectory: string | null, contractsOnly: boolean): string[] {
    if (!moduleDirectory) {
        return ['--testPathPatterns', `/tests/${contractsOnly ? CONTRACT_SPECS : ''}`, '--coverage=false'];
    }

    const root = moduleDirectory.replace(/\/+$/, '');

    return [
        '--testPathPatterns',
        `${root}/tests/${contractsOnly ? CONTRACT_SPECS : ''}`,
        '--coverage',
        '--collectCoverageFrom',
        `${root}/src/**/*.{js,ts}`,
        '--coverageReporters',
        'text',
    ];
}
