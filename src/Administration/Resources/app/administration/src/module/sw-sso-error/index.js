/**
 * @sw-package after-sales
 */
import './page/index';

import deDE from "./snippet/de-DE.json";
import enGB from "./snippet/en-GB.json";

const { Module } = Shopware;
// TODO: REMOVE AFTER DEBUG
console.log('logLogLog');
// TODO: REMOVE AFTER DEBUG
/**
 * @private
 */
Module.register('sw-sso-error', {
    type: 'core',
    name: 'sso-error',
    title: 'sw-sso-error.general.title',
    description: 'sw-sso-error.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#f1122c',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            components: {
                default: 'sw-sso-error-index',
            },
            path: '',
        },
    },
})
