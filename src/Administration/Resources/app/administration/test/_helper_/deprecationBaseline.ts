/**
 * @sw-package framework
 * @private
 */
import { appendFileSync } from 'node:fs';
import { join } from 'node:path';
import deprecationBaseline from 'src/meta/deprecation-baseline.json';
import { isMajorRun, loadFeatureConfig } from './majorFeatureFlags';
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
 * The known usages of a deprecated API, per usage site the runs that hit it.
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

// The hits this worker appended, so a usage read on every render is appended only once per hits file.
const recordedHits = new Set<string>();

/**
 * The key of a deprecation in the baseline: the message without the component path the deprecation plugin
 * appends (`Used in: …`), so one key covers every place the deprecated API is used.
 *
 * @private
 * @example
 * deprecationBaselineKey('The component "sw-x" is deprecated.\nUsed in: sw-page > sw-x'); // 'The component "sw-x" is deprecated.'
 */
export function deprecationBaselineKey(message: string): string {
    return message.split('\nUsed in: ')[0];
}

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
 * Whether the checked-in baseline lists the usage site of `entry` as a known usage of its deprecation in the
 * current run, also with `DEPRECATION_BASELINE=update`.
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
    return process.env.DEPRECATION_BASELINE === 'update' || isListed(entry);
}

/**
 * Tells `deprecationBaselineReporter.js` that the baseline let the usage of `entry` pass, through this worker's
 * file in the directory the reporter created. Without the reporter, e.g. in a spec, it does nothing.
 *
 * @private
 * @example
 * recordBaselineHit({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' });
 */
export function recordBaselineHit({ message, usageSite }: Pick<RecordedDeprecation, 'message' | 'usageSite'>): void {
    const hitsDirectory = process.env.DEPRECATION_BASELINE_HITS;

    if (!hitsDirectory) {
        return;
    }

    const hitsFile = join(hitsDirectory, `${process.pid}.jsonl`);
    const hit = JSON.stringify([deprecationBaselineKey(message), usageSite]);

    if (recordedHits.has(`${hitsFile} ${hit}`)) {
        return;
    }

    recordedHits.add(`${hitsFile} ${hit}`);
    appendFileSync(hitsFile, `${hit}\n`);
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
 * Restores the baseline to the checked-in `src/meta/deprecation-baseline.json`, dropping every entry
 * `baselineDeprecation()` listed.
 *
 * @private
 * @example
 * beforeEach(resetBaseline);
 */
export function resetBaseline(): void {
    baseline = JSON.parse(checkedInBaseline) as Record<string, BaselinedUsages>;
}
