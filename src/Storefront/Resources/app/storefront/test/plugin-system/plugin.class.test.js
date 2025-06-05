import Plugin from 'src/plugin-system/plugin.class';
import StringHelper from 'src/helper/string.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';
import deepmerge from 'deepmerge';

// Mock dependencies
jest.mock('src/helper/string.helper');
jest.mock('src/helper/emitter.helper');
jest.mock('deepmerge');

// Create a test plugin class that extends the base Plugin class
class TestPlugin extends Plugin {
    init() {
        this.initialized = true;
    }
}

// Create a plugin class that doesn't implement init
class InvalidPlugin extends Plugin {
    // init method intentionally not implemented
}

describe('Plugin class', () => {
    let element;
    let plugin;
    let mockEmitter;
    
    beforeEach(() => {
        // Reset mocks
        jest.clearAllMocks();
        
        // Setup DOM
        document.body.innerHTML = '<div id="test-element"></div>';
        element = document.getElementById('test-element');
        
        // Mock NativeEventEmitter
        mockEmitter = {
            on: jest.fn(),
            off: jest.fn(),
            emit: jest.fn()
        };
        NativeEventEmitter.mockImplementation(() => mockEmitter);
        
        // Mock StringHelper
        StringHelper.toDashCase.mockImplementation((str) => str.toLowerCase());
        
        // Mock deepmerge
        deepmerge.all.mockImplementation((configs) => {
            return configs.reduce((result, config) => ({ ...result, ...config }), {});
        });
        
        // Setup global objects needed by Plugin
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({
                get: jest.fn().mockReturnValue([])
            })
        };
        
        window.PluginConfigManager = {
            get: jest.fn().mockReturnValue({})
        };
    });

    describe('constructor', () => {
        it('should log a warning if no valid element is provided', () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

            new Plugin(null, {}, 'TestPlugin');
            expect(consoleSpy).toHaveBeenCalledWith('There is no valid element given while trying to create a plugin instance for "TestPlugin".');

            new Plugin(undefined, {}, 'TestPlugin');
            expect(consoleSpy).toHaveBeenCalledWith('There is no valid element given while trying to create a plugin instance for "TestPlugin".');

            new Plugin('not-an-element', {}, 'TestPlugin');
            expect(consoleSpy).toHaveBeenCalledWith('There is no valid element given while trying to create a plugin instance for "TestPlugin".');
 
            consoleSpy.mockRestore();
        });

        it('should initialize with valid element and default options', () => {
            plugin = new TestPlugin(element);
            
            expect(plugin.el).toBe(element);
            expect(plugin._pluginName).toBe('TestPlugin');
            expect(plugin._initialized).toBe(true);
            expect(plugin.initialized).toBe(true);
            expect(NativeEventEmitter).toHaveBeenCalledWith(element);
        });
        
        it('should use provided plugin name if specified', () => {
            plugin = new TestPlugin(element, {}, 'CustomPluginName');
            
            expect(plugin._pluginName).toBe('CustomPluginName');
        });
        
        it('should merge options correctly', () => {
            const options = { testOption: 'value' };
            TestPlugin.options = { defaultOption: 'default' };
            
            plugin = new TestPlugin(element, options);
            
            expect(deepmerge.all).toHaveBeenCalled();
            expect(plugin.options).toEqual(expect.objectContaining({
                defaultOption: 'default',
                testOption: 'value'
            }));
        });
        
        it('should work with document as the element', () => {
            // Create a plugin with document as the element
            plugin = new TestPlugin(document, { documentOption: 'value' });
            
            // Verify the plugin is initialized correctly
            expect(plugin.el).toBe(document);
            expect(plugin._pluginName).toBe('TestPlugin');
            expect(plugin._initialized).toBe(true);
            expect(plugin.initialized).toBe(true);
            expect(NativeEventEmitter).toHaveBeenCalledWith(document);

            // Verify options are merged correctly
            expect(deepmerge.all).toHaveBeenCalled();
            expect(plugin.options).toEqual(expect.objectContaining({
                documentOption: 'value'
            }));

            expect(plugin._getConfigFromDataAttribute()).toEqual({});
            expect(plugin._getOptionsFromDataAttribute()).toEqual({});

            // Verify that PluginConfigManager.get is not called
            expect(window.PluginConfigManager.get).not.toHaveBeenCalled();
        });
    });
    
    describe('initialization', () => {
        it('should call init method during initialization', () => {
            const initSpy = jest.spyOn(TestPlugin.prototype, 'init');
            
            plugin = new TestPlugin(element);
            
            expect(initSpy).toHaveBeenCalled();
            expect(plugin._initialized).toBe(true);
        });

        it('should log a warning if init method is not implemented', () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

            new InvalidPlugin(element);

            expect(consoleSpy).toHaveBeenCalledWith(
                'The "init" method for the plugin "InvalidPlugin" is not defined. The plugin will not be initialized.'
            );

            consoleSpy.mockRestore();
        });
        
        it('should not initialize twice', () => {
            const initSpy = jest.spyOn(TestPlugin.prototype, 'init');
            
            plugin = new TestPlugin(element);
            plugin._init(); // Call _init again
            
            expect(initSpy).toHaveBeenCalledTimes(1);
        });
    });
    
    describe('update method', () => {
        it('should call update method when plugin is initialized', () => {
            const updateSpy = jest.spyOn(TestPlugin.prototype, 'update');
            
            plugin = new TestPlugin(element);
            plugin._update();
            
            expect(updateSpy).toHaveBeenCalled();
        });
        
        it('should not call update method when plugin is not initialized', () => {
            const updateSpy = jest.spyOn(TestPlugin.prototype, 'update');
            
            plugin = new TestPlugin(element);
            plugin._initialized = false;
            plugin._update();
            
            expect(updateSpy).not.toHaveBeenCalled();
        });
    });
    
    describe('options handling', () => {
        it('should get options from data attributes', () => {
            element.setAttribute('data-testplugin-options', '{"attrOption": "value"}');
            
            plugin = new TestPlugin(element);
            
            expect(deepmerge.all).toHaveBeenCalledWith(
                expect.arrayContaining([
                    expect.objectContaining({ attrOption: 'value' })
                ])
            );
        });
        
        it('should handle invalid JSON in data attributes', () => {
            element.setAttribute('data-testplugin-options', 'invalid-json');
            
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            plugin = new TestPlugin(element);
            
            expect(consoleSpy).toHaveBeenCalledWith(
                expect.stringContaining('could not be parsed to json')
            );
        });
        
        it('should get config from data attributes', () => {
            element.setAttribute('data-testplugin-config', 'config-name');
            window.PluginConfigManager.get.mockReturnValue({ configOption: 'value' });
            
            plugin = new TestPlugin(element);
            
            expect(window.PluginConfigManager.get).toHaveBeenCalledWith('TestPlugin', 'config-name');
            expect(deepmerge.all).toHaveBeenCalledWith(
                expect.arrayContaining([
                    expect.objectContaining({ configOption: 'value' })
                ])
            );
        });

        it('should merge options when using document as element', () => {
            TestPlugin.options = { defaultOption: 'default' };

            // Create plugin with document as element
            plugin = new TestPlugin(document, { documentOption: 'value' });

            // Verify options are merged correctly
            expect(deepmerge.all).toHaveBeenCalled();
            expect(plugin.options).toEqual(expect.objectContaining({
                defaultOption: 'default',
                documentOption: 'value'
            }));

            expect(plugin._getConfigFromDataAttribute()).toEqual({});
            expect(plugin._getOptionsFromDataAttribute()).toEqual({});
            
            // Verify that data attributes are ignored
            expect(window.PluginConfigManager.get).not.toHaveBeenCalled();
        });
    });
    
    describe('plugin registration', () => {
        it('should register the plugin instance with the element', () => {
            const elementPluginInstances = new Map();
            window.PluginManager.getPluginInstancesFromElement.mockReturnValue(elementPluginInstances);
            
            const pluginInstances = [];
            window.PluginManager.getPlugin.mockReturnValue({
                get: jest.fn().mockReturnValue(pluginInstances)
            });
            
            plugin = new TestPlugin(element);
            
            expect(elementPluginInstances.get('TestPlugin')).toBe(plugin);
            expect(pluginInstances).toContain(plugin);
        });
        
        it('should register the plugin instance with document when using document as element', () => {
            const documentPluginInstances = new Map();
            window.PluginManager.getPluginInstancesFromElement.mockReturnValue(documentPluginInstances);
            
            const pluginInstances = [];
            window.PluginManager.getPlugin.mockReturnValue({
                get: jest.fn().mockReturnValue(pluginInstances)
            });
            
            plugin = new TestPlugin(document);
            
            expect(documentPluginInstances.get('TestPlugin')).toBe(plugin);
            expect(pluginInstances).toContain(plugin);
        });
    });
});
