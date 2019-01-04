const merge = require('webpack-merge');
const devEnv = require('./dev.env');
const flags = require('./../build/utils').loadFeatureFlags(process.env.ENV_FILE);

module.exports = merge(devEnv, {
    FLAGS: `'${JSON.stringify(flags)}'`,
    NODE_ENV: '"testing"',
    APP_URL: `'${process.env.APP_URL}'`
});
