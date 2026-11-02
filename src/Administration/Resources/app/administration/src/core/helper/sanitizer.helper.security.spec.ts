/**
 * @sw-package framework
 */

import DOMPurify from 'dompurify';

describe('DOMPurify security', () => {
    it.each(['parent', 'sibling'])('neutralizes a %s subtree detached by a hook', (target) => {
        const root = document.createElement('div');
        const parent = document.createElement('section');
        const sibling = document.createElement('aside');
        const trigger = document.createElement('span');
        const image = document.createElement('img');
        image.setAttribute('onerror', 'alert(1)');

        parent.append(trigger);
        root.append(parent, sibling);

        const subtree = target === 'parent' ? parent : sibling;
        subtree.append(image);

        DOMPurify.addHook('beforeSanitizeElements', (node) => {
            if (node === trigger) {
                subtree.remove();
            }
        });

        try {
            DOMPurify.sanitize(root, { IN_PLACE: true });

            expect(subtree.parentNode).toBeNull();
            expect(image.hasAttribute('onerror')).toBe(false);
        } finally {
            DOMPurify.removeHooks('beforeSanitizeElements');
        }
    });
});
