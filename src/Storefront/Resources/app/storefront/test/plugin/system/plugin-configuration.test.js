/* eslint-disable */
import template from './plugin-configuration.template.html';
import Plugin from "../../../src/plugin-system/plugin.class";

class PluginConfigurationPlugin extends Plugin {
    static options = {
        isSet: true
    };

    init() {}
}

describe('Load plugin configuration from empty array', () => {
    let configurationPlugin = undefined;

    beforeEach(() => {
        document.body.innerHTML = template;

        configurationPlugin = new PluginConfigurationPlugin(
            document.querySelector('#test'),
            null,
            'PluginConfiguration'
        );
    });

    afterEach(() => {
        configurationPlugin = undefined;
    });

    test('configuration plugin exists', () => {
        expect(typeof configurationPlugin).toBe('object');
    });

    test('_isSet is set via options', () => {
        expect(configurationPlugin.options.isSet).toBe(true);
    });
});
