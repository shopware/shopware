/**
 * @package admin
 *
 * @module core/service/utils/sort
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    afterSort,
};

interface SortElements {
    id: string;
    data: {
        [key: string]: unknown;
    };
}

/**
 * Sorts the elements by their after id property chain
 *
 * @param {array} elements
 * @param {string} property
 * @returns {array}
 */
function afterSort(elements: SortElements[], property = 'afterId'): SortElements[] {
    if (elements.length === 0) {
        return elements;
    }

    // pre-sort elements to pull elements without an after id parent to the front
    elements.sort((a, b) => {
        if (a.data[property] === b.data[property] && a.data[property] === null) {
            return 0;
        }

        if (b.data[property] === null) {
            return 1;
        }

        if (a.data[property] === null) {
            return -1;
        }

        return 0;
    });

    // add first element to sorted list as this will be the absolute first item
    const firstItem = elements.shift();
    const sortedElements = [firstItem];

    let lastId = firstItem && firstItem.id;

    while (elements.length > 0) {
        let loop = true;
        /* eslint-disable no-loop-func */
        elements.forEach((leaf, key) => {
            if (leaf.data[property] !== lastId) {
                return;
            }

            // find the next element in the chain and set it as the new parent
            sortedElements.push(leaf);
            lastId = leaf.id;

            elements.splice(key, 1);
            loop = false;
        });
        /* eslint-enable no-loop-func */

        // chain is broken, continue with next element as parent
        if (loop) {
            const nextItem = elements.shift();

            sortedElements.push(nextItem);

            if (!elements.length) {
                break;
            }

            lastId = nextItem && (nextItem.data[property] as string);
        }
    }

    return sortedElements as SortElements[];
}
