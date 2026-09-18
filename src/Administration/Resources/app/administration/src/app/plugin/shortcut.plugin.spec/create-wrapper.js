/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';

// systemKey() reads the platform, and SYSTEMKEY maps to CTRL only on macOS.
Object.defineProperty(window.navigator, 'platform', { value: 'MacIntel', configurable: true });

Shopware.Utils.debounce = function debounce(callback, delay) {
    let timeout = null;

    const execFunction = jest.fn(() => {
        clearTimeout(timeout);
        timeout = setTimeout(callback, delay);
    });
    execFunction.cancel = jest.fn(() => clearTimeout(timeout));

    return execFunction;
};

/**
 * Mounts a component with the shortcut plugin installed, attached to the document so a keydown
 * triggered on the wrapper bubbles to the registry's listener.
 *
 * @private
 */
export default async function createWrapper(componentOverride = {}) {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...componentOverride,
    };

    const element = document.createElement('div');
    if (document.body) {
        document.body.appendChild(element);
    }

    return mount(baseComponent, {
        attachTo: element,
        global: {
            plugins: [shortcutPlugin],
        },
    });
}
