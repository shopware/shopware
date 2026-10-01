/**
 * @sw-package framework
 */
import type { ElementQuery } from '../types';

const AnchorTags: ElementQuery = (mutations: MutationRecord[]): Element[] => {
    const anchorTags: Element[] = [];

    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) {
                return;
            }

            if (node.tagName.toLowerCase() === 'a') {
                anchorTags.push(node);
            }

            node.querySelectorAll('a').forEach((anchor) => {
                anchorTags.push(anchor);
            });
        });
    });

    return anchorTags;
};

/**
 * @private
 */
export default AnchorTags;
