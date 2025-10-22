/* eslint-disable no-console */

/**
 * @sw-package framework
 */
class FailedSpecFileReporter {
    constructor(globalConfig, options) {
        this._globalConfig = globalConfig;
        this._options = options;
        this.failedFiles = new Set();
    }

    onTestResult(test, testResult) {
        if (testResult.numFailingTests > 0) {
            this.failedFiles.add(testResult.testFilePath);
        }
    }

    onRunComplete() {
        if (this.failedFiles.size > 0) {
            // Using console.error to make output more visible in watch mode
            console.error('\n\n');
            console.error('\x1b[41m\x1b[37m FAILED FILES SUMMARY \x1b[0m');
            console.error('\x1b[31m===================\x1b[0m');

            Array.from(this.failedFiles)
                .sort()
                .forEach(file => {
                    // Using relative path for cleaner output
                    const relativePath = file.split('/').slice(-3).join('/');
                    console.error(`\x1b[31m❌ ${relativePath}\x1b[0m`);
                });

            console.error(`\n\x1b[31mTotal failed files: ${this.failedFiles.size}\x1b[0m`);
            console.error('\n');
        }

        // Clear the set for the next run
        this.failedFiles.clear();
    }
}

module.exports = FailedSpecFileReporter;
