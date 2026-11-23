/**
 * @sw-package framework
 */
import path from 'path';
import TwigjsPlugin from './index';

describe('build/vite-plugins/twigjs-plugin', () => {
    it('should be a function with 0 arguments', () => {
        expect(typeof TwigjsPlugin).toBe('function');

        // check that the function has 0 arguments
        expect(TwigjsPlugin).toHaveLength(0);
    });

    it('should return an object with a name and transform property', () => {
        const plugin = TwigjsPlugin();

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('shopware-vite-plugin-twigjs');

        // Check if the plugin has a transform method
        expect(plugin).toHaveProperty('transform');
    });

    it('should not transform the index.html of the Administration', async () => {
        const plugin = TwigjsPlugin();
        const fileContent = '<!DOCTYPE html><html><head><meta charset="utf-8"></head></html>';
        const id = path.resolve(__dirname, '../../../index.html');

        const result = await plugin.transform(fileContent, id);

        expect(result).toBeUndefined();
    });

    it('should transform other index.html files', async () => {
        const plugin = TwigjsPlugin();
        const fileContent = 'file content';
        const id = '/var/www/html/custom/plugins/SwagExample/src/Resources/app/administration/src/index.html';

        const result = await plugin.transform(fileContent, id);

        expect(result.code).toBe(`export default "${fileContent}"`);
    });

    it('should transform twig file', async () => {
        const plugin = TwigjsPlugin();
        const fileContent = 'file content';
        const id = 'src/Administration/Resources/app/administration/index.twig';

        const result = await plugin.transform(fileContent, id);

        expect(result).toHaveProperty('code');
        expect(result.code).toBe(`export default "${fileContent}"`);
    });

    it('should transform twig file changing the content', async () => {
        const plugin = TwigjsPlugin();
        const fileContent = `
            {% block content %}
            <div>
                <!-- single line comment -->
                <h1>Test</h1>


                <!--
                    multi-line comment

                    -->

                <input type="text" value="test">
            </div>
            {% endblock %}
        `;
        const id = 'src/Administration/Resources/app/administration/index.twig';

        const result = await plugin.transform(fileContent, id);

        expect(result).toHaveProperty('code');
        expect(result.code).toBe(
            `export default "{% block content %} <div> <h1>Test</h1> <input type=\\"text\\" value=\\"test\\"> </div> {% endblock %}"`,
        );
    });

    describe('in the dev server', () => {
        const id = 'src/Administration/Resources/app/administration/src/sw-example.html.twig';

        // Runs the served module with a stand-in for `import.meta.hot` and returns its default export.
        function runServedModule(code, hot) {
            const body = code.replace('export default template;', '').replaceAll('import.meta.hot', 'hot');

            return new Function('hot', `${body}\nreturn template;`)(hot);
        }

        async function serve(fileContent) {
            const plugin = TwigjsPlugin();
            plugin.configResolved({ command: 'serve' });

            return (await plugin.transform(fileContent, id)).code;
        }

        function createHot() {
            const hot = { accept: jest.fn(), invalidate: jest.fn() };
            hot.applyUpdate = async (nextTemplate) => {
                hot.accept.mock.calls[0][0]({ default: nextTemplate });
                await flushPromises();
            };

            return hot;
        }

        afterEach(() => {
            delete global.Shopware.Application.view;
        });

        it('exports the normalized template', async () => {
            expect(runServedModule(await serve('<div>  Test </div>'), createHot())).toBe('<div> Test </div>');
        });

        it('hands an edited template to the view', async () => {
            const hotUpdateTemplate = jest.fn(() => Promise.resolve(true));
            global.Shopware.Application.view = { hotUpdateTemplate };
            const hot = createHot();
            runServedModule(await serve('<div>Before</div>'), hot);

            await hot.applyUpdate('<div>After</div>');

            expect(hotUpdateTemplate).toHaveBeenCalledWith('<div>Before</div>', '<div>After</div>');
            expect(hot.invalidate).not.toHaveBeenCalled();
        });

        it.each([
            ['the view cannot apply the edit', () => ({ hotUpdateTemplate: () => Promise.resolve(false) })],
            ['applying the edit fails', () => ({ hotUpdateTemplate: () => Promise.reject(new Error('broken')) })],
            ['the view does not support hot updates', () => ({})],
        ])('reloads when %s', async (_, createView) => {
            global.Shopware.Application.view = createView();
            const hot = createHot();
            runServedModule(await serve('<div>Before</div>'), hot);

            await hot.applyUpdate('<div>After</div>');

            expect(hot.invalidate).toHaveBeenCalledTimes(1);
        });

        it('keeps production modules free of hot update code', async () => {
            const plugin = TwigjsPlugin();
            plugin.configResolved({ command: 'build' });

            expect((await plugin.transform('<div>Test</div>', id)).code).toBe('export default "<div>Test</div>"');
        });
    });
});
