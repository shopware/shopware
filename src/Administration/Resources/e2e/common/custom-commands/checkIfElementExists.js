/**
 * Checks if an element is existent, without causing the test to fail
 *
 * @param {String} selector
 * @param callback
 * @returns {exports}
 */
exports.command = function checkIfElementExists(selector, callback = () => {}) {
    const self = this;

    // Check if selector is present and defining the callback accordingly
    this.execute(function find(elSelector) {
        return document.querySelector(elSelector) !== null;
    }, [selector], function queryResult(result) {
        callback.call(self, result);
    });

    return this;
};
