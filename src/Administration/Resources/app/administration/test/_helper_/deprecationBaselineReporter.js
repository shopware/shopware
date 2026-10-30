/**
 * @sw-package framework
 * @private
 *
 * Keeps `src/core/deprecation-baseline.json` in step with the specs (see `deprecationBaseline.ts`). After a full,
 * green run it fails on usages the baseline lists for this run that no spec needed, or with
 * `DEPRECATION_BASELINE=update` rewrites this run's markings from the usages the workers let pass.
 */
const { readFileSync, writeFileSync } = require('node:fs');
const { isBaselineUpdateRun } = require('./isBaselineUpdateRun');
const {
    startCollectingUsedBaselineEntries,
    stopCollectingUsedBaselineEntries,
    usedBaselineEntries,
} = require('./usedBaselineEntries');

class DeprecationBaselineReporter {
    /**
     * @param {object} globalConfig Jest's global config, to tell a full run from a filtered one.
     * @param {{ baselinePath: string, run: 'default' | 'major' }} options `run`: whether the run activates a major.
     */
    constructor(globalConfig, { baselinePath, run }) {
        this.globalConfig = globalConfig;
        this.baselinePath = baselinePath;
        this.run = run;
    }

    onRunStart() {
        this.error = undefined;
        // The workers start after this, so they record into this run's collection.
        startCollectingUsedBaselineEntries();
    }

    onRunComplete(_testContexts, results) {
        const usedEntries = usedBaselineEntries();
        stopCollectingUsedBaselineEntries();
        const isUpdate = isBaselineUpdateRun();

        if (!isFullRun(this.globalConfig)) {
            if (isUpdate) {
                this.fail(
                    `DEPRECATION_BASELINE=update needs a full run without filters: DEPRECATION_BASELINE=update ${updateCommand(this.run)}`,
                );
            } else if (process.env.CI) {
                // CI logs show the check did not run; locally, filtered runs are the norm.
                this.log('Deprecation baseline: skipped the stale check, because the run is filtered.');
            }

            return;
        }

        // A failed spec may have stopped before some of its usages, so its used entries are incomplete.
        if (!isGreen(results)) {
            if (isUpdate) {
                this.fail(`DEPRECATION_BASELINE=update did not write ${this.baselinePath}: fix the failing specs first.`);
            }

            return;
        }

        const baseline = JSON.parse(readFileSync(this.baselinePath, 'utf8'));

        if (isUpdate) {
            writeFileSync(this.baselinePath, `${JSON.stringify(markRun(baseline, usedEntries, this.run), null, 4)}\n`);
            this.log(`Deprecation baseline: marked the ${usedEntries.length} usages of the ${this.run} run.`);

            return;
        }

        const stale = findStale(baseline, usedEntries, this.run);

        if (stale.length > 0) {
            this.fail(
                `The deprecation baseline lists usages no spec of the ${this.run} run needs anymore. ` +
                    `Remove "${this.run}" from them in ${this.baselinePath}, or run:\n` +
                    `    DEPRECATION_BASELINE=update ${updateCommand(this.run)}\n` +
                    `Stale usages:\n${stale.join('\n')}`,
            );

            return;
        }

        this.log(
            `Deprecation baseline: checked the ${countListed(baseline, this.run)} usages listed for the ${this.run} run.`,
        );
    }

    /**
     * Prints a line next to Jest's summary, the way Jest's own reporters do.
     */
    log(line) {
        process.stderr.write(`${line}\n`);
    }

    /**
     * Jest marks the run as failed when a reporter returns an error, but does not print it.
     */
    fail(message) {
        console.error(message);
        this.error = new Error(message);
    }

    getLastError() {
        return this.error;
    }
}

/**
 * Whether every spec ran. A filtered run cannot tell a stale entry from one whose spec it skipped.
 * `--changedSince` and `--lastCommit` set `onlyChanged`, and `--findRelatedTests` takes its files as `nonFlagArgs`.
 *
 * @example
 * isFullRun(globalConfig); // false for `jest src/app`
 */
function isFullRun(globalConfig) {
    return ![
        globalConfig.nonFlagArgs.length > 0,
        globalConfig.testPathPatterns.isSet(),
        globalConfig.testNamePattern,
        globalConfig.onlyChanged,
        globalConfig.onlyFailures,
        globalConfig.shard,
        // Not in the global config: they filter the projects, or the spec files of a project.
        process.argv.some((arg) => /^--(test-?path-?ignore-?patterns|select-?projects)\b/i.test(arg)),
    ].some(Boolean);
}

/**
 * Whether every spec ran through: no failed test, no suite that crashed, no error of Jest itself, no interrupted
 * watch run.
 *
 * @example
 * isGreen(results); // false after a crashed suite
 */
function isGreen(results) {
    return (
        results.numFailedTests === 0 && results.numFailedTestSuites === 0 && !results.runExecError && !results.wasInterrupted
    );
}

/**
 * The composer command whose update run rewrites the markings of `run`.
 *
 * @example
 * updateCommand('major'); // 'composer admin:unit:major'
 */
function updateCommand(run) {
    return run === 'major' ? 'composer admin:unit:major' : 'composer admin:unit';
}

/**
 * The usages the baseline lists for `run` that are missing from `usedEntries`, as `<site>: <message>`.
 *
 * @example
 * findStale({ 'old() is deprecated.': { 'sw-page': ['major'] } }, [], 'major'); // ['sw-page: old() is deprecated.']
 */
function findStale(baseline, usedEntries, run) {
    const used = new Set(usedEntries.map((entry) => JSON.stringify(entry)));

    return Object.entries(baseline).flatMap(([message, sites]) =>
        Object.entries(sites)
            .filter(([site, runs]) => runs.includes(run) && !used.has(JSON.stringify([message, site])))
            .map(([site]) => `${site}: ${message}`),
    );
}

/**
 * How many usages the baseline lists for `run`.
 *
 * @example
 * countListed({ 'old() is deprecated.': { 'sw-page': ['major'], 'sw-author': ['default'] } }, 'major'); // 1
 */
function countListed(baseline, run) {
    return Object.values(baseline).flatMap((sites) => Object.values(sites).filter((runs) => runs.includes(run))).length;
}

/**
 * `baseline` with `run` marked on exactly the usages in `usedEntries`, keeping the markings of the other run. A usage
 * no run marks anymore is dropped.
 *
 * @example
 * markRun({ 'old() is deprecated.': { 'sw-page': ['default'] } }, [['old() is deprecated.', 'sw-page']], 'major');
 * // { 'old() is deprecated.': { 'sw-page': ['default', 'major'] } }
 */
function markRun(baseline, usedEntries, run) {
    const marked = {};
    const mark = (message, site, markedRun) => {
        marked[message] = marked[message] ?? {};
        marked[message][site] = [...(marked[message][site] ?? []), markedRun];
    };

    Object.entries(baseline).forEach(([message, sites]) => {
        Object.entries(sites).forEach(([site, runs]) => {
            runs.filter((otherRun) => otherRun !== run).forEach((otherRun) => mark(message, site, otherRun));
        });
    });
    usedEntries.forEach(([message, site]) => mark(message, site, run));

    return sorted(marked);
}

/**
 * `baseline` with its messages, sites and runs sorted, so a rewrite diffs only where usages changed.
 *
 * @example
 * sorted({ 'b()': { 'sw-b': ['major'] }, 'a()': { 'sw-a': ['major', 'default'] } });
 * // { 'a()': { 'sw-a': ['default', 'major'] }, 'b()': { 'sw-b': ['major'] } }
 */
function sorted(baseline) {
    return Object.fromEntries(
        Object.keys(baseline)
            .sort()
            .map((message) => [
                message,
                Object.fromEntries(
                    Object.keys(baseline[message])
                        .sort()
                        .map((site) => [site, [...baseline[message][site]].sort()]),
                ),
            ]),
    );
}

module.exports = DeprecationBaselineReporter;
