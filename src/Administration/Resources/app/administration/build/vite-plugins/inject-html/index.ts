import type { HtmlTagDescriptor, Plugin } from 'vite';

const injectHtml: (injections: HtmlTagDescriptor[]) => Plugin = (injections) => {
    return {
        name: 'shopware-vite-plugin-inject-html',
        transformIndexHtml() {
            return injections;
        },
    };
};

/**
 * @private
 */
export default injectHtml;
