/**
 * A better `clearValue` for inputs having a more complex interaction.
 *
 * @export
 * @param {string} selector
 * @returns
 */

exports.command = function clearValueManual (selector) {
    const { RIGHT_ARROW, BACK_SPACE } = this.Keys;
    return this.getValue(selector, (result) => {
        const chars = result.value.split('');
        // Make sure we are at the end of the input
        chars.forEach(() => this.setValue(selector, RIGHT_ARROW));
        // Delete all the existing characters
        chars.forEach(() => this.setValue(selector, BACK_SPACE));
    })
};