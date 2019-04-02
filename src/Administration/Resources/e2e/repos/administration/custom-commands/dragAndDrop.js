/**
 * Drags one element and drops it on another
 *
 * Usage:
 * ```
 * .dragAndDrop(
 *      `${page.elements.dataGridColumn}--2 ${page.elements.dataGridColumn}-resize`,
 *      `${page.elements.dataGridColumn}--2 ${page.elements.contextMenuButton}`,
 *      { xDrag: 0 }
 * );
 * ```
 *
 * @param {String} draggableElement
 * @param {String} elementToDropTo
 * @param {Object} [obj=null]
 * @param {String} obj.xDrag
 * @param {String} obj.xDrop
 */
module.exports.command = function dragAndDrop(draggableElement, elementToDropTo, {
    xDrag = 10, yDrag = 10
}) {
    this.getLocation(elementToDropTo, (result) => {
        const xDrop = result.value.x;
        const yDrop = result.value.y;

        this.moveToElement(draggableElement, xDrag, yDrag).mouseButtonDown('left')
            .moveToElement(elementToDropTo, xDrop, yDrop)
            .mouseButtonUp('left');
        return this;
    });

    return this;
};
