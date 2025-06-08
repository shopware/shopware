/**
 * @package admin
 */

const path = require('path');
const process = require('process');

module.exports = {
    tags: {
        allowUnknownTags: true,
        dictionaries: ['jsdoc', 'closure'],
    },
    plugins: [],
    templates: {
        cleverLinks: false,
        monospaceLinks: false,
        default: {
            outputSourceFiles: true,
        },
    },
    opts: {
        destination: path.join(process.env.PROJECT_ROOT, '/build/artifacts/jsdoc'),
        encoding: 'utf8',
        private: true,
        recurse: true,
        template: './node_modules/docdash',
        verbose: true,
    },
};
