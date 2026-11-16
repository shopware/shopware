/**
 * @sw-package framework
 */

/**
 * Option schema of every deprecation rule that can fix. 'enableFix' is the default; it stays accepted
 * so configs that spell it out keep validating.
 *
 * @example
 * 'sw-deprecation-rules/no-deprecated-js-api': ['error', 'disableFix'] // report only
 */
const FIX_OPTION_SCHEMA = [
    {
        enum: [
            'disableFix',
            'enableFix',
        ],
    },
];

/**
 * Returns the object option of a deprecation rule, or an empty object: `{ fix, activatedComponents }`
 * of no-deprecated-components, `{ ids, level }` of the upgrade codemods.
 *
 * @example
 * getObjectOption({ options: [{ ids: ['component.sw-tabs'] }] }); // { ids: ['component.sw-tabs'] }
 */
function getObjectOption(context) {
    const [option] = context.options ?? [];

    return option && typeof option === 'object' ? option : {};
}

/**
 * Tells whether the rule runs report-only, from either option form: `'disableFix'` or
 * `{ fix: false }` (used by no-deprecated-components, whose options are an object).
 *
 * @example
 * isFixDisabled({ options: ['disableFix'] }); // true
 */
function isFixDisabled(context) {
    return context.options.includes('disableFix') || getObjectOption(context).fix === false;
}

/**
 * Tells whether `--fix` may rewrite a registry step: the rule may fix and the step is not 'manual'.
 * Every rule that fixes registry steps goes through this, so a 'manual' step is never rewritten.
 *
 * @example
 * canAutoFix(context, { fix: 'unsafe-auto' }); // true unless the rule runs with 'disableFix'
 */
function canAutoFix(context, usage) {
    return !isFixDisabled(context) && usage?.fix !== 'manual';
}

module.exports = {
    FIX_OPTION_SCHEMA,
    canAutoFix,
    getObjectOption,
    isFixDisabled,
};
