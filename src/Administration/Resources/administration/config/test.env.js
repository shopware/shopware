const merge = require('webpack-merge');
const devEnv = require('./dev.env');

const appURL = process.env.APP_URL;
module.exports = merge(devEnv, {
    NODE_ENV: '"testing"',
    BASE_PATH: `"${appURL}"`
});
