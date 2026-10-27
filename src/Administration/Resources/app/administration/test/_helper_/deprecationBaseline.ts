/**
 * @sw-package framework
 * @private
 */
import deprecationBaseline from 'src/core/deprecation-baseline.json';
import { deprecationBaselineKey } from 'src/core/feature';
import { isBaselineUpdateRun } from './isBaselineUpdateRun';
import { isMajorRun, loadFeatureConfig } from './majorFeatureFlags';
import { recordUsedBaselineEntry as recordUsedEntry } from './usedBaselineEntries';
import type { RecordedDeprecation } from './deprecations';

/**
 * A Jest run of the Administration: `major` activates a major flag (`composer admin:unit:major`), `default`
 * does not (`composer admin:unit`).
 *
 * @private
 * @example
 * const run: DeprecationRun = 'major';
 */
export type DeprecationRun = 'default' | 'major';

/**
 * The known usages of a deprecated API, per usage site the runs that use it.
 *
 * @private
 * @example
 * { 'sw-order-list > sw-loader': ['default', 'major'], 'src/app/main.ts': ['major'] }
 */
export type BaselinedUsages = Record<string, DeprecationRun[]>;

const featureConfig = loadFeatureConfig();

// Deprecation key → known usages. The checked-in file stays untouched; `baseline` is the copy specs may extend.
// A JSON copy, not `structuredClone`: plugin Jest setups load this file without the jsdom polyfill for it.
const checkedInBaseline = JSON.stringify(deprecationBaseline);
let baseline = JSON.parse(checkedInBaseline) as Record<string, BaselinedUsages>;

/**
 * The run this Jest worker belongs to, from the feature flags its environment activates.
 *
 * @private
 * @example
 * currentRun(); // 'major' in `composer admin:unit:major`
 */
export function currentRun(): DeprecationRun {
    return isMajorRun(featureConfig, process.env) ? 'major' : 'default';
}

/**
 * Whether the baseline lists the usage site of `entry` as a known usage of its deprecation in the current run,
 * also with `DEPRECATION_BASELINE=update`.
 *
 * @private
 * @example
 * isListed({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' }); // false
 */
export function isListed({ message, usageSite }: Pick<RecordedDeprecation, 'message' | 'usageSite'>): boolean {
    return baseline[deprecationBaselineKey(message)]?.[usageSite]?.includes(currentRun()) ?? false;
}

/**
 * Whether the baseline lets the usage of `entry` pass without an assertion: it is listed for the current run.
 * With `DEPRECATION_BASELINE=update`, every usage is, as the run rewrites the baseline from them.
 *
 * @private
 * @example
 * isBaselined({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' }); // false
 */
export function isBaselined(entry: Pick<RecordedDeprecation, 'message' | 'usageSite'>): boolean {
    return isBaselineUpdateRun() || isListed(entry);
}

/**
 * Tells `deprecationBaselineReporter.js` that the baseline let the usage of `entry` pass, under its baseline key.
 *
 * @private
 * @example
 * recordUsedBaselineEntry({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' });
 */
export function recordUsedBaselineEntry({ message, usageSite }: Pick<RecordedDeprecation, 'message' | 'usageSite'>): void {
    recordUsedEntry({ message: deprecationBaselineKey(message), usageSite });
}

/**
 * Lists `message` in the baseline with exactly `callSites` as its known usages, for specs that cover how the
 * baseline behaves. Pair it with `beforeEach(resetBaseline)`: a reset in `afterEach` may run before the setup
 * checks the test's deprecations.
 *
 * @private
 * @example
 * baselineDeprecation({ message: 'oldFunction() is deprecated.', callSites: { 'sw-page > sw-old': ['default', 'major'] } });
 */
export function baselineDeprecation({ message, callSites }: { message: string; callSites: BaselinedUsages }): void {
    baseline[message] = callSites;
}

/**
 * Restores the baseline to the checked-in `src/core/deprecation-baseline.json`, dropping every entry
 * `baselineDeprecation()` listed.
 *
 * @private
 * @example
 * beforeEach(resetBaseline);
 */
export function resetBaseline(): void {
    baseline = JSON.parse(checkedInBaseline) as Record<string, BaselinedUsages>;
}
