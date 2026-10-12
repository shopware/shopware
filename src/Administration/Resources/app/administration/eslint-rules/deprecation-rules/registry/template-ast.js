/**
 * @sw-package framework
 */

/**
 * Finds the object `v-bind="…"` attribute (a `v-bind` without argument) on an element's start tag.
 * It can hide or override any prop, so fixes that add or rename props on that element need a review.
 *
 * @example
 * findObjectVBindAttribute(node); // the VAttribute for <sw-field v-bind="fieldProps" />, null for <sw-field :label="x" />
 */
function findObjectVBindAttribute(node) {
    return (
        node?.startTag?.attributes?.find((attribute) => {
            return (
                attribute.type === 'VAttribute' &&
                attribute.directive === true &&
                attribute.key?.name?.name === 'bind' &&
                !attribute.key.argument
            );
        }) ?? null
    );
}

module.exports = {
    findObjectVBindAttribute,
};
