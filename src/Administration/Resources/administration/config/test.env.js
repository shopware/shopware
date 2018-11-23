const merge = require('webpack-merge');
const devEnv = require('./dev.env');
const flags = require('./../build/utils').loadFeatureFlags(process.env.ENV_FILE);

const appURL = process.env.APP_URL;
module.exports = merge(devEnv, {
    FLAGS: `'${JSON.stringify(flags)}'`,
    NODE_ENV: '"testing"',
    BASE_PATH: `"${appURL}"`
});
