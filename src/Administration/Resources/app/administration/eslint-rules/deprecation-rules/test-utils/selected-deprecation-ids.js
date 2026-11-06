/**
 * @sw-package framework
 */

const { SELECTED_IDS_VARIABLE } = require('../registry/filter-migrations');

/**
 * Sets the deprecation id selection that `deprecation:migrate --id` passes to ESLint and returns a
 * function that restores the previous value, deleting the variable when it was unset before.
 *
 * @example
 * const restore = selectDeprecationIds('component.sw-alert');
 * restore();
 */
function selectDeprecationIds(value) {
    const hadValue = Object.prototype.hasOwnProperty.call(process.env, SELECTED_IDS_VARIABLE);
    const previousValue = process.env[SELECTED_IDS_VARIABLE];

    if (value === undefined) {
        delete process.env[SELECTED_IDS_VARIABLE];
    } else {
        process.env[SELECTED_IDS_VARIABLE] = value;
    }

    return () => {
        if (hadValue) {
            process.env[SELECTED_IDS_VARIABLE] = previousValue;
        } else {
            delete process.env[SELECTED_IDS_VARIABLE];
        }
    };
}

/**
 * Runs rule tests in a describe block that sees only the given deprecation ids selected, the way a
 * `deprecation:migrate --id <id>` run narrows every registry rule. Other test blocks are unaffected.
 *
 * @example
 * describeWithSelectedDeprecationIds('component.sw-alert', () => tester.run('rule', rule, cases));
 */
function describeWithSelectedDeprecationIds(ids, defineTests) {
    describe(`with ${SELECTED_IDS_VARIABLE}=${ids}`, () => {
        let restore;

        beforeAll(() => {
            restore = selectDeprecationIds(ids);
        });

        afterAll(() => {
            restore();
        });

        defineTests();
    });
}

module.exports = {
    describeWithSelectedDeprecationIds,
    selectDeprecationIds,
};
