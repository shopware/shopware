/**
 * @sw-package framework
 */
import InjectHtmlPlugin from './index';

describe('build/vite-plugins/inject-html', () => {
    it('should return an object with a name and a transformIndexHtml function', () => {
        const injections = [];
        const plugin = InjectHtmlPlugin(injections);

        expect(plugin).toEqual({
            name: 'shopware-vite-plugin-inject-html',
            transformIndexHtml: expect.any(Function),
        });
    });

    it('should return the injections in the transformIndexHtml function', () => {
        const injections = [
            {
                tag: 'script',
                attrs: {
                    src: 'https://example.com/script.js',
                },
            },
        ];
        const plugin = InjectHtmlPlugin(injections);
        const transformedHtml = plugin.transformIndexHtml();

        expect(transformedHtml).toEqual(injections);
    });
});
