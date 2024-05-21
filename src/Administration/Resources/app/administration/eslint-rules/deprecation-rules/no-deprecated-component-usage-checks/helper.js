function findShortHandSlot(node, slotName) {
    return node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === slotName
            });
    });
}

module.exports = {
    findShortHandSlot
}
