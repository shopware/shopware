/**
 * Decides which elements belong to scripts instead of the Twig output.
 *
 * Scripts own the elements they add at runtime, for example backdrops, pseudo modals and toasts.
 * A morph must neither remove them nor turn them into server markup.
 *
 * @sw-package framework
 * @private
 */

export const PRESERVE_ATTRIBUTE = 'data-sw-twig-hmr-preserve';

/**
 * Elements that Bootstrap and Storefront scripts create before the Twig hot reload can observe them,
 * or that a template marks as owned by scripts.
 */
export const CLIENT_OWNED_SELECTOR = [
    '.modal-backdrop',
    '.offcanvas-backdrop',
    '.js-pseudo-modal',
    '.js-offcanvas-singleton',
    `[${PRESERVE_ATTRIBUTE}]`,
].join(',');

/**
 * @param {HTMLElement} root - observed for elements that scripts add
 */
export function createOwnership(root) {
    const addedByScripts = new WeakSet();

    function collect(records) {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    addedByScripts.add(node);
                }
            });
        });
    }

    const observer = new MutationObserver(collect);
    observer.observe(root, { childList: true, subtree: true });

    return {
        /**
         * Fragments are inserted by scripts as a whole, so inside a fragment only the known selectors count.
         *
         * @param {Element} element
         * @param {boolean} insideFragment
         * @returns {boolean}
         */
        isClientOwned(element, insideFragment = false) {
            return element.matches(CLIENT_OWNED_SELECTOR) || (!insideFragment && addedByScripts.has(element));
        },

        /**
         * @param {Element} element
         * @param {Element} root - the element that the morph updates
         * @param {boolean} insideFragment
         * @returns {boolean} true if the element or one of its ancestors below the root belongs to scripts
         */
        isInsideClientOwned(element, root, insideFragment = false) {
            for (let current = element; current && current !== root; current = current.parentElement) {
                if (this.isClientOwned(current, insideFragment)) {
                    return true;
                }
            }

            return false;
        },

        /**
         * Runs the morph without recording its own insertions as elements of scripts.
         *
         * @template T
         * @param {function(): T} callback
         * @returns {T}
         */
        withoutRecording(callback) {
            collect(observer.takeRecords());

            try {
                return callback();
            } finally {
                observer.takeRecords();
            }
        },

        disconnect() {
            observer.disconnect();
        },
    };
}
