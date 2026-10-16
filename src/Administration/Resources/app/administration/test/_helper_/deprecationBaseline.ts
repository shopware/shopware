/**
 * @sw-package framework
 * @private
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';
import deprecationBaseline from 'src/meta/deprecation-baseline.json';
import { isMajorRun } from './majorFeatureFlags';
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

const featureConfig = parse(
    readFileSync(
        resolve(process.env.ADMIN_PATH as string, '../../../../Core/Framework/Resources/config/packages/feature.yaml'),
        'utf8',
    ),
) as Parameters<typeof isMajorRun>[0];

// Deprecation key → known usages, as in `src/meta/deprecation-baseline.json`.
const baseline = deprecationBaseline as Record<string, BaselinedUsages>;
const checkedInBaseline = JSON.stringify(baseline);

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
 * Whether the baseline lists the usage site of `entry` as a known usage of its deprecation in the current run.
 *
 * @private
 * @example
 * isBaselined({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' }); // false
 */
export function isBaselined({ message, usageSite }: Pick<RecordedDeprecation, 'message' | 'usageSite'>): boolean {
    return baseline[deprecationBaselineKey(message)]?.[usageSite]?.includes(currentRun()) ?? false;
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
    Object.keys(baseline).forEach((message) => delete baseline[message]);
    Object.assign(baseline, JSON.parse(checkedInBaseline));
}
