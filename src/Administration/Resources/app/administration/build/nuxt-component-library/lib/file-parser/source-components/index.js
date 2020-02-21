const extractComponentDeclaration = require('./extractComponentDeclaration');
const extractComputed = require('./extractComputed');
const extractImports = require('./extractImports');
const extractInject = require('./extractInject');
const extractLifecycleHooks = require('./extractLifecycleHooks');
const extractMethods = require('./extractMethods');
const extractMixins = require('./extractMixins');
const extractProps = require('./extractProps');
const extractDeprecations = require('./extractDeprecations');
const extractWatcher = require('./extractWatcher');
const parseSource = require('./parseSource');
const extractBlockComment = require('./extractBlockComment');

module.exports = {
    extractComponentDeclaration,
    extractComputed,
    extractImports,
    extractInject,
    extractLifecycleHooks,
    extractMethods,
    extractMixins,
    extractProps,
    extractDeprecations,
    extractWatcher,
    parseSource,
    extractBlockComment
};
