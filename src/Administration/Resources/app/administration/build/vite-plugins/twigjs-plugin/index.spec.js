/**
 * @sw-package framework
 */
import path from 'path';
import TwigjsPlugin from './index';

describe('build/vite-plugins/twigjs-plugin', () => {
    it('should be a function with 0 arguments', () => {
        expect(typeof TwigjsPlugin).toBe('function');

        // check that the function has 0 arguments
        expect(TwigjsPlugin.length).toBe(0);
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
});
