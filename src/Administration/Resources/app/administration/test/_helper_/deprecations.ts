/**
 * @sw-package framework
 * @private
 */

import type { DeprecationOptions } from 'src/core/feature';
import { getDefaultActiveFeatureFlags } from './defaultActiveFeatureFlags';
import normalizeFeatureFlag from './normalizeFeatureFlag';
import { location, ownFrames, usageSite } from './deprecationUsageSite';
import { isBaselined, isListed, recordUsedBaselineEntry } from './deprecationBaseline';
import { isBaselineUpdateRun } from './isBaselineUpdateRun';

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
 * { message: 'oldFunction() is deprecated.', error: new Error(), usageSite: 'sw-page > sw-old', asserted: false }
 */
export type RecordedDeprecation = {
    /** The guard's message, with component names as the running Administration shows them. */
    message: string;
    /**
     * Where the guard was called, so a failure lists the frames that lead to the deprecated API. Its stack is read
     * only for `usageSite` outside a component and for the failure report, because formatting a stack is expensive.
     */
    error: Error;
    /** Where the deprecated API is used, see `test/_helper_/deprecationUsageSite.ts`. */
    usageSite: string;
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
    return typeof expected === 'string' ? message.includes(expected) : message.search(expected) !== -1;
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
 * @returns `false` when the deprecation is not recorded because of `it.activeFeatureFlags()`, an unknown major
 * flag the production guard reports as a typo, outside a test, or after `useProductionDeprecationGuard()`
 *
 * @private
 * @example
 * if (shouldHandleDeprecationsThroughMock(majorFlag, Feature.isActive(majorFlag), Feature.isKnownMajor(majorFlag))) { recordDeprecation(message); return; }
 */
export function shouldHandleDeprecationsThroughMock(
    majorFlag: string,
    isMajorActive: boolean,
    isMajorKnown: boolean,
): boolean {
    const normalizedMajor = normalizeFeatureFlag(majorFlag);
    const isActivatedByTest =
        isMajorActive && !getDefaultActiveFeatureFlags().some((flag) => normalizeFeatureFlag(flag) === normalizedMajor);

    return deprecations.isRecording && isMajorKnown && !isActivatedByTest;
}

/**
 * Records a triggered deprecation for the current test.
 *
 * @private
 * @example
 * recordDeprecation('oldFunction() is deprecated.', options?._blame);
 */
export function recordDeprecation(message: string, blame?: DeprecationOptions['_blame']): void {
    const error = new Error();
    deprecations.entries.push({
        message: withoutWrappedSuffix(message),
        error,
        usageSite: usageSite(error, blame),
        asserted: false,
    });
}

/**
 * Whether a recorded deprecation fails the test
 * It fails the test if it was neither explicitly asserted nor allowed by `allowDeprecation(...)` nor baselined
 *
 * @private
 * @example
 * deprecations.entries.filter((entry) => failsTheTest(entry, deprecations.allowed));
 */
export function failsTheTest(entry: RecordedDeprecation, allowed: readonly Expected[]): boolean {
    return isUnasserted(entry, allowed) && !isBaselined(entry);
}

/**
 * Whether the test neither asserted nor allowed `entry`, so only the baseline can let it pass.
 *
 * @example
 * isUnasserted(entry, deprecations.allowed);
 */
function isUnasserted(entry: RecordedDeprecation, allowed: readonly Expected[]): boolean {
    return !entry.asserted && !allowed.some((expected) => matches(expected, entry.message));
}

/**
 * Whether the baseline still needs `entry`, so its reporter keeps the usage listed: a listed usage always does,
 * even when the test asserted or allowed it, as the running Administration reaches it too. An update run adds
 * only the usages the test neither asserted nor allowed.
 *
 * @example
 * deprecations.entries.filter((entry) => needsBaselineEntry(entry, deprecations.allowed));
 */
function needsBaselineEntry(entry: RecordedDeprecation, allowed: readonly Expected[]): boolean {
    return isListed(entry) || (isBaselineUpdateRun() && isUnasserted(entry, allowed));
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
 * Ends the log of the current test and clears it with its allowances, then throws one error listing every
 * deprecation that fails the test, each with where it is used and the frames that lead there. The usages the
 * deprecation baseline still needs go to its reporter. The Jest setup calls it after each test.
 *
 * @private
 * @example
 * afterEach(endDeprecationLog);
 */
export function endDeprecationLog(): void {
    const { entries, allowed } = deprecations;
    entries.filter((entry) => needsBaselineEntry(entry, allowed)).forEach(recordUsedBaselineEntry);
    const failing = entries.filter((entry) => failsTheTest(entry, allowed));

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
        ...failing.map(({ message: deprecation, error: usage, usageSite: site }, index) =>
            [
                `\n${index + 1}. ${deprecation}`,
                `   used in ${site}`,
                ...ownFrames(usage.stack).map((frame) => `   ↳ ${location(frame)}`),
            ].join('\n'),
        ),
    ].join('\n');
    const error = new Error(message);

    // Jest prints a code frame from the stack, so it shows the first deprecation instead of this hook.
    error.stack = [`Error: ${message}`, ...ownFrames(failing[0].error.stack).map(({ text }) => text)].join('\n');

    throw error;
}

/**
 * `expect(deprecations).toHaveTriggered(expected)`: passes when a deprecation of the current test matches
 * `expected`, and marks every matching one as asserted. Like `toThrow`, a string matches part of the message.
 * It must match exactly one deprecation, which the test may have recorded several times.
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
    const matchedMessages = [...new Set(matching.map(({ message }) => message))];
    const isAmbiguous = !this.isNot && matchedMessages.length > 1;
    const pass = matching.length > 0 && !isAmbiguous;

    if (pass) {
        matching.forEach((entry) => {
            entry.asserted = true;
        });
    }

    const printList = (messages: string[]) => messages.map((message) => `  ${this.utils.printReceived(message)}`);
    const recorded = printList(received.entries.map(({ message }) => message));
    const expectation = isAmbiguous
        ? [
              `Expected one deprecation matching ${this.utils.printExpected(expected)}, but it matches ${matchedMessages.length}:`,
              ...printList(matchedMessages),
              'Use a more specific message.',
          ]
        : [`Expected ${pass ? 'no deprecation' : 'a deprecation'} matching: ${this.utils.printExpected(expected)}`];

    return {
        pass,
        message: () =>
            [
                hint,
                '',
                ...expectation,
                `Deprecations recorded in this test:${recorded.length > 0 ? `\n${recorded.join('\n')}` : ' none'}`,
            ].join('\n'),
    };
}
