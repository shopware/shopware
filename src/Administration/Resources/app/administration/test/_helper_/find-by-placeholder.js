/**
 * @sw-package framework
 * @private
 */
function findByPlaceholder(wrapper, placeholderText) {
    return wrapper.find(
        `input[placeholder="${placeholderText}"], textarea[placeholder="${placeholderText}"], select[placeholder="${placeholderText}"]`,
    );
}

export default findByPlaceholder;
