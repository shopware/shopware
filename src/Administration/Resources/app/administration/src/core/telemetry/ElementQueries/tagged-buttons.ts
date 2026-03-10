/**
 * @sw-package framework
 */
import type { ElementQuery } from '../types';

const TaggedButtons: ElementQuery = (mutations: MutationRecord[]): Element[] => {
    const taggedButtons: Element[] = [];

    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) {
                return;
            }

            if (node.tagName.toLowerCase() === 'button' && node.getAttribute('data-analytics-id')) {
                taggedButtons.push(node);
            }

            node.querySelectorAll('button[data-analytics-id]').forEach((button) => {
                taggedButtons.push(button);
            });
        });
    });

    return taggedButtons;
};

/**
 * @private
 */
export default TaggedButtons;
