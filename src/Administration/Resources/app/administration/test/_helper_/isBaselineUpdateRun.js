/**
 * @sw-package framework
 * @private
 */

/**
 * Whether the Jest run rewrites the deprecation baseline (`DEPRECATION_BASELINE=update`) instead of checking it.
 * Plain CommonJS, so the reporter and the workers read the mode the same way.
 *
 * @private
 * @returns {boolean}
 * @example
 * isBaselineUpdateRun(); // true in `DEPRECATION_BASELINE=update composer admin:unit`
 */
function isBaselineUpdateRun() {
    return process.env.DEPRECATION_BASELINE === 'update';
}

module.exports = { isBaselineUpdateRun };
