/**
 * @sw-package framework
 *
 * Jest `testResultsProcessor` of `admin:test-extensions`: writes the passed and
 * failed test counts to the file named by `SW_TEST_EXTENSIONS_RESULTS_FILE`.
 * Jest's own `--outputFile` would print that temp file's path after every run.
 */

const fs = require('fs');

/**
 * @param {{ numPassedTests: number, numFailedTests: number }} results
 * @returns {object} the unchanged results, as Jest expects from a processor
 */
module.exports = (results) => {
    fs.writeFileSync(
        process.env.SW_TEST_EXTENSIONS_RESULTS_FILE,
        JSON.stringify({ numPassedTests: results.numPassedTests, numFailedTests: results.numFailedTests }),
    );

    return results;
};
