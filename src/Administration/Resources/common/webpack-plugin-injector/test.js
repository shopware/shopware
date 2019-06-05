const WebpackPluginInjector = require('./index');

const file = '/Applications/MAMP/htdocs/sw-next-development/var/plugins.json';
const webpackConfig = require('../../administration/build/webpack.base.conf');

new WebpackPluginInjector(file, webpackConfig, 'administration');
