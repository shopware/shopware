// Enable ES6 features
require('babel-register')({
    ignore: [/node_modules/]
});

const fs = require('fs');
const process = require('process');
const path = require('path');
const merge = require('lodash/merge');

const env = process.env.NIGHTWATCH_ENV;
const repoPath = `${__dirname}/repos`;

// Add additional path to the node module path resolver.
require('app-module-path').addPath(`${repoPath}`);

// Get all directories from the `repo` directory
const repoDirs = fs.readdirSync(repoPath).filter((file) => {
    return fs.statSync(path.join(repoPath, file)).isDirectory();
});

// Load default config
let config = require('./common/nightwatch.conf.js');

// Check if the E2E_ENV is set, it sets up the config file for the specific tests
if (!env || env.length <= 0) {
    console.log('No "NIGHTWATCH_ENV" environment parameter set which is necessary');
    process.exit(1);
}

// Check if we have the directory matching the `env` variable.
if (!repoDirs.includes(env)) {
    console.log(`Directory "${env}" was not found in "${repoPath}"`);
    process.exit(1);
}

// Check if we have a custom config file and merge it with the default config
if (fs.existsSync(`${repoPath}/${env}/nightwatch.conf.js`)) {
    const envConfig = require(`${repoPath}/${env}/nightwatch.conf.js`);
    config = merge(envConfig, config);
}

// Export the merged config
module.exports = config;
