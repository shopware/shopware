/**
 * @sw-package framework
 * @private
 *
 * The used baseline entries: the `[message, usage site]` pairs the deprecation baseline let pass. The Jest workers
 * record them and `deprecationBaselineReporter.js` reads them. Plain CommonJS, as Jest loads reporters untransformed.
 */
const { appendFileSync, existsSync, mkdtempSync, readdirSync, readFileSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');

// The workers inherit it from the process that started the run, so it tells them where to record.
const DIRECTORY_VARIABLE = 'DEPRECATION_BASELINE_USED_ENTRIES';

/** @type {string | undefined} */
let startedDirectory;

// Per directory, so a usage read on every render is appended only once.
const appended = new Set();

/**
 * Starts collecting used baseline entries from this process and every worker it starts afterwards, replacing what
 * this process collected before. The reporter calls it when a run starts; a spec calls it before each test.
 *
 * @private
 * @example
 * beforeEach(startCollectingUsedBaselineEntries);
 */
function startCollectingUsedBaselineEntries() {
    stopCollectingUsedBaselineEntries();
    startedDirectory = mkdtempSync(join(tmpdir(), 'deprecation-baseline-'));
    process.env[DIRECTORY_VARIABLE] = startedDirectory;
}

/**
 * Records that the baseline let a usage pass. Does nothing while nothing collects,
 * e.g. when Jest runs without the reporter (`--reporters=default`).
 *
 * @private
 * @param {{ message: string, usageSite: string }} entry `message`: the deprecation's baseline key.
 * @returns {void}
 * @example
 * recordUsedBaselineEntry({ message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' });
 */
function recordUsedBaselineEntry({ message, usageSite }) {
    const directory = process.env[DIRECTORY_VARIABLE];

    if (!directory) {
        return;
    }

    const line = JSON.stringify([message, usageSite]);

    if (appended.has(`${directory} ${line}`)) {
        return;
    }

    appended.add(`${directory} ${line}`);
    appendFileSync(join(directory, `${process.pid}.jsonl`), `${line}\n`);
}

/**
 * Every entry any process recorded since collecting started, as sorted `[message, usage site]` pairs without duplicates.
 * The reporter reads them when the run completes.
 *
 * @private
 * @returns {[string, string][]}
 * @example
 * usedBaselineEntries(); // [['oldFunction() is deprecated.', 'src/app/main.ts']]
 */
function usedBaselineEntries() {
    const directory = process.env[DIRECTORY_VARIABLE];

    if (!directory || !existsSync(directory)) {
        return [];
    }

    const lines = readdirSync(directory).flatMap((file) =>
        readFileSync(join(directory, file), 'utf8').split('\n').filter(Boolean),
    );

    return [...new Set(lines)].sort().map((line) => JSON.parse(line));
}

/**
 * Stops collecting and removes what this process collected.
 *
 * @private
 * @returns {void}
 * @example
 * afterAll(stopCollectingUsedBaselineEntries);
 */
function stopCollectingUsedBaselineEntries() {
    if (startedDirectory) {
        rmSync(startedDirectory, { recursive: true, force: true });
    }

    startedDirectory = undefined;
    appended.clear();
    delete process.env[DIRECTORY_VARIABLE];
}

module.exports = {
    startCollectingUsedBaselineEntries,
    recordUsedBaselineEntry,
    usedBaselineEntries,
    stopCollectingUsedBaselineEntries,
};
