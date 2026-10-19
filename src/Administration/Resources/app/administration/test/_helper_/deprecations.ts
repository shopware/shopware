/**
 * @sw-package framework
 * @private
 */

import { getDefaultActiveFeatureFlags } from './defaultActiveFeatureFlags';
import normalizeFeatureFlag from './normalizeFeatureFlag';

/**
 * What an assertion or allowance matches a deprecation message against: a string matches part of it, a RegExp
 * is tested against it, like in `toThrow`.
 *
 * @example
 * 'The old usage of "sw-loader" is deprecated'
 */
type Expected = string | RegExp;

/**
 * One call of the deprecation guard during the current test. Read it only when a failure message is not enough.
 *
 * @private
 * @example
 * { message: 'oldFunction() is deprecated.', error: new Error(), asserted: false }
 */
export type RecordedDeprecation = {
    /** The guard's message, with component names as the running Administration shows them. */
    message: string;
    /**
     * Where the guard was called, so a failure points at the line that used the deprecated API. Its stack is read
     * only for the failure report, because formatting a stack is expensive and most entries never fail the test.
     */
    error: Error;
    /** A passing `toHaveTriggered()` matched it. */
    asserted: boolean;
};

/**
 * The deprecations the current test ran into. Read it through `expect(deprecations).toHaveTriggered()`
 * rather than its fields; its only instance is `deprecations`.
 *
 * @private
 * @example
 * expect(deprecations).toHaveTriggered('oldFunction() is deprecated.');
 */
export class DeprecationLog {
    /** Every guard call of the current test, in order. */
    readonly entries: RecordedDeprecation[] = [];

    /** What `allowDeprecation()` registered for the current test. */
    readonly allowed: Expected[] = [];

    /**
     * While off, the guard warns or throws as in production: outside a test, which has no `afterEach` to report
     * to, and after `useProductionDeprecationGuard()`.
     */
    isRecording = false;
}

/**
 * Handle you need to import to assert a deprecation (see example)
 *
 * @private
 * @example
 * import { deprecations } from 'test/_helper_/deprecations';
 *
 * it('renders the legacy loader', async () => {
 *     const wrapper = await createWrapper();
 *
 *     expect(deprecations).toHaveTriggered('The old usage of "sw-loader" is deprecated');
 *     expect(wrapper.find('.sw-loader').exists()).toBe(true);
 * });
 */
export const deprecations = new DeprecationLog();

/** Between the setup's `startDeprecationLog` and `endDeprecationLog`, so while a test or its hooks run. */
let isTestRunning = false;

/**
 * Throws unless a test is running, because the setup resets what `helper` changes before every test.
 *
 * @example
 * assertTestIsRunning('allowDeprecation');
 */
function assertTestIsRunning(helper: string): void {
    if (!isTestRunning) {
        throw new Error(`${helper}() only works inside a test or its beforeEach: the setup resets it before every test.`);
    }
}

/**
 * Whether `message` matches `expected`, for both the assertion and the allowances.
 *
 * @example
 * matches('sw-loader', 'The old usage of "sw-loader" is deprecated'); // true
 */
function matches(expected: Expected, message: string): boolean {
    return typeof expected === 'string' ? message.includes(expected) : expected.test(message);
}

/**
 * Silences a specific deprecation for the current test. Only use it in a spec that tests a deprecated component itself.
 * Throws outside a test or its `beforeEach`.
 *
 * @private
 * @example
 * import { allowDeprecation } from 'test/_helper_/deprecations';
 *
 * describe('src/app/component/base/sw-empty-state', () => {
 *     beforeEach(() => {
 *         allowDeprecation('The component "sw-empty-state" is deprecated');
 *     });
 * });
 */
export function allowDeprecation(expected: Expected): void {
    assertTestIsRunning('allowDeprecation');
    deprecations.allowed.push(expected);
}

/**
 * Disables the mock that can silence baselined or explicitly asserted deprecations.
 * Use it to test the `triggerDeprecationOrThrow` guard itself.
 * Throws outside a test or its `beforeEach`.
 *
 * @private
 * @example
 * import { useProductionDeprecationGuard } from 'test/_helper_/deprecations';
 *
 * beforeEach(() => {
 *     useProductionDeprecationGuard();
 * });
 */
export function useProductionDeprecationGuard(): void {
    assertTestIsRunning('useProductionDeprecationGuard');
    deprecations.isRecording = false;
}

/**
 * `message` without the `__wrapped` suffix `wrapTestComponent` adds to component names, so specs assert the name
 * the running Administration shows.
 *
 * @example
 * withoutWrappedSuffix('The component "sw-empty-state__wrapped" is deprecated'); // 'The component "sw-empty-state" is deprecated'
 */
function withoutWrappedSuffix(message: string): string {
    return message.replace(/__wrapped\b/g, '');
}

/**
 * Whether to record a triggered deprecation
 * Won't record it if the major flag was explicitly activated by `it.activeFeatureFlags()` so it can still throw
 * @returns `false` when the deprecation is not recorded because of `it.activeFeatureFlags()`, outside a test, or
 * after `useProductionDeprecationGuard()`
 *
 * @private
 * @example
 * if (shouldHandleDeprecationsThroughMock(majorFlag, this.isActive(majorFlag))) { recordDeprecation(message); return; }
 */
export function shouldHandleDeprecationsThroughMock(majorFlag: string, isMajorActive: boolean): boolean {
    const isActivatedByTest =
        isMajorActive && !getDefaultActiveFeatureFlags().map(normalizeFeatureFlag).includes(normalizeFeatureFlag(majorFlag));

    return deprecations.isRecording && !isActivatedByTest;
}

/**
 * Records a triggered deprecation for the current test.
 *
 * @private
 * @example
 * recordDeprecation('oldFunction() is deprecated.');
 */
export function recordDeprecation(message: string): void {
    deprecations.entries.push({ message: withoutWrappedSuffix(message), error: new Error(), asserted: false });
}

/**
 * Whether a recorded deprecation fails the test
 * It fails the test if it was neither explicitly asserted nor allowed by `allowDeprecation(...)`
 *
 * @private
 * @example
 * deprecations.entries.filter((entry) => failsTheTest(entry, deprecations.allowed));
 */
export function failsTheTest(entry: RecordedDeprecation, allowed: readonly Expected[]): boolean {
    return !entry.asserted && !allowed.some((expected) => matches(expected, entry.message));
}

/**
 * Starts recording the deprecations of a test. The Jest setup calls it before each test.
 *
 * @private
 * @example
 * beforeEach(startDeprecationLog);
 */
export function startDeprecationLog(): void {
    isTestRunning = true;
    deprecations.isRecording = true;
}

/**
 * @returns the call stack within the project (filtering out noise: node_modules and helper functions that will be there always)
 *
 * @example
 * usageFrames(entry.error); // ['    at oldFunction (/…/oldFunction.ts:13:13)', '    at … (/…/my.spec.ts:10:20)']
 */
function usageFrames(error: Error): string[] {
    return (error.stack ?? '')
        .split('\n')
        .filter((line) => /^\s+at /.test(line) && line.includes(process.cwd()))
        .filter((line) => !/node_modules|_helper_\/deprecations\.ts|feature\.service\.mock\.js/.test(line));
}

/**
 * The `file:line:column` of a stack frame, relative to the project, for the failure report.
 *
 * @example
 * location('    at oldFunction (/app/test/oldFunction.ts:13:13)'); // 'test/oldFunction.ts:13:13'
 */
function location(frame: string): string {
    return (/\(?(\S+:\d+:\d+)\)?$/.exec(frame)?.[1] ?? frame.trim()).replace(`${process.cwd()}/`, '');
}

/**
 * Ends the log of the current test and clears it with its allowances, then throws one error listing every
 * deprecation that fails the test, each with the frames that lead to its usage. The Jest setup calls it after
 * each test.
 *
 * @private
 * @example
 * afterEach(endDeprecationLog);
 */
export function endDeprecationLog(): void {
    const failing = deprecations.entries.filter((entry) => failsTheTest(entry, deprecations.allowed));

    isTestRunning = false;
    deprecations.isRecording = false;
    deprecations.entries.length = 0;
    deprecations.allowed.length = 0;

    if (failing.length === 0) {
        return;
    }

    const message = [
        'The test ran into deprecations it did not assert. Fix each usage, or assert it with',
        '`expect(deprecations).toHaveTriggered(message)` from `test/_helper_/deprecations`.',
        ...failing.map(({ message: deprecation, error: usage }, index) =>
            [`\n${index + 1}. ${deprecation}`, ...usageFrames(usage).map((frame) => `   ↳ ${location(frame)}`)].join('\n'),
        ),
    ].join('\n');
    const error = new Error(message);

    // Jest prints a code frame from the stack, so it shows the first deprecation instead of this hook.
    error.stack = [`Error: ${message}`, ...usageFrames(failing[0].error)].join('\n');

    throw error;
}

/**
 * `expect(deprecations).toHaveTriggered(expected)`: passes when a deprecation of the current test matches
 * `expected`, and marks every matching one as asserted. Like `toThrow`, a string matches part of the message.
 * A broad string asserts every matching deprecation.
 * Registered in `test/_setup/jest-extensions.ts`.
 *
 * @private
 * @example
 * expect(deprecations).toHaveTriggered(/"sw-loader" is deprecated/);
 * expect(deprecations).not.toHaveTriggered('The old usage of "sw-loader" is deprecated');
 */
export function toHaveTriggered(this: jest.MatcherContext, received: unknown, expected: Expected): jest.CustomMatcherResult {
    const hint = this.utils.matcherHint('toHaveTriggered', 'deprecations', 'expected', { isNot: this.isNot });

    if (!(received instanceof DeprecationLog)) {
        throw new TypeError(
            this.utils.matcherErrorMessage(
                hint,
                `${this.utils.RECEIVED_COLOR('received')} value must be \`deprecations\` from test/_helper_/deprecations`,
                this.utils.printWithType('Received', received, this.utils.printReceived),
            ),
        );
    }

    const matching = received.entries.filter((entry) => matches(expected, entry.message));
    matching.forEach((entry) => {
        entry.asserted = true;
    });

    const recorded = received.entries.map(({ message }) => `  ${this.utils.printReceived(message)}`);
    const pass = matching.length > 0;

    return {
        pass,
        message: () =>
            [
                hint,
                '',
                `Expected ${pass ? 'no deprecation' : 'a deprecation'} matching: ${this.utils.printExpected(expected)}`,
                `Deprecations recorded in this test:${recorded.length > 0 ? `\n${recorded.join('\n')}` : ' none'}`,
            ].join('\n'),
    };
}
