import ComponentFactory from 'src/core/factory/component.factory';
import TemplateFactory from 'src/core/factory/template.factory';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

beforeEach(() => {
    ComponentFactory.getComponentRegistry().clear();
    ComponentFactory.getOverrideRegistry().clear();
    TemplateFactory.getTemplateRegistry().clear();
    TemplateFactory.disableTwigCache();
});

describe('core/factory/component.factory.js', () => {
    it(
        'should register a component and it should be registered in the component registry',
        () => {
            const component = ComponentFactory.register('test-component', {
                template: '<div>This is a test template.</div>'
            });

            const registry = ComponentFactory.getComponentRegistry();

            expect(typeof component).toBe('object');
            expect(registry.has('test-component')).toBe(true);
            expect(typeof registry.get('test-component')).toBe('object');
        }
    );

    it(
        'should not be possible to register a component with the same name twice',
        () => {
            const compDefinition = {
                template: '<div>This is a test template.</div>'
            };

            ComponentFactory.register('test-component', compDefinition);
            const component = ComponentFactory.register('test-component', compDefinition);

            expect(component).toBe(false);
        }
    );

    it('should not be possible to register a component without a name', () => {
        const component = ComponentFactory.register('', {
            template: '<div>This is a test template.</div>'
        });

        expect(component).toBe(false);
    });

    it(
        'should not be possible to register a component without a template',
        () => {
            const component = ComponentFactory.register('test-component', {});

            expect(component).toBe(false);
        }
    );

    it(
        'should not have a template property after registering a component',
        () => {
            const component = ComponentFactory.register('test-component', {
                template: '<div>This is a test template.</div>'
            });

            expect(component.template).toBe(undefined);
        }
    );

    xit('should extend a given component & should register a new component (without template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {}
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(typeof extension.updated).toBe('function');
        expect(extension.extends).toBeInstanceOf('String');
        expect(extension.extends).toBe('test-component');
        expect(registry.has('test-component-extension')).toBe(true);
        expect(typeof registry.get('test-component-extension')).toBe('object');
    });

    xit('should extend a given component & should register a new component (with template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {},
            template: '<div>This is an extension.</div>'
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(typeof extension.updated).toBe('function');
        expect(extension.extends).toBeInstanceOf('String');
        expect(extension.extends).toBe('test-component');
        expect(registry.has('test-component-extension')).toBe(true);
        expect(typeof registry.get('test-component-extension')).toBe('object');
        expect(extension.template).toBe(undefined);
    });

    it(
        'should register an override of an existing component in the override registry (without index)',
        () => {
            ComponentFactory.register('test-component', {
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    }
                },
                template: '<div>This is a test template.</div>'
            });

            const override = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is an override.';
                    }
                },
                template: '<div>This is an override.</div>'
            });

            const registry = ComponentFactory.getComponentRegistry();
            const overrideRegistry = ComponentFactory.getOverrideRegistry();

            expect(typeof override.methods.testMethod).toBe('function');
            expect(override.template).toBe(undefined);
            expect(registry.has('test-component')).toBe(true);
            expect(typeof registry.get('test-component')).toBe('object');
            expect(overrideRegistry.has('test-component')).toBe(true);
            expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
            expect(overrideRegistry.get('test-component').length).toBe(1);
            expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
        }
    );

    it(
        'should register two overrides of an existing component in the override registry (with index)',
        () => {
            ComponentFactory.register('test-component', {
                created() {},
                methods: {
                    testMethod() {
                        return 'This is a test method.';
                    }
                },
                template: '<div>This is a test template.</div>'
            });

            const overrideOne = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is the first override.';
                    }
                }
            });

            const overrideTwo = ComponentFactory.override('test-component', {
                methods: {
                    testMethod() {
                        return 'This is the second override.';
                    }
                }
            }, 0);

            const registry = ComponentFactory.getComponentRegistry();
            const overrideRegistry = ComponentFactory.getOverrideRegistry();

            expect(typeof overrideOne.methods.testMethod).toBe('function');
            expect(typeof overrideTwo.methods.testMethod).toBe('function');
            expect(overrideOne.template).toBe(undefined);
            expect(overrideTwo.template).toBe(undefined);
            expect(registry.has('test-component')).toBe(true);
            expect(registry.get('test-component')).toBeInstanceOf(Object);
            expect(overrideRegistry.has('test-component')).toBe(true);
            expect(overrideRegistry.get('test-component')).toBeInstanceOf(Array);
            expect(overrideRegistry.get('test-component').length).toBe(2);
            expect(overrideRegistry.get('test-component')[0]).toBeInstanceOf(Object);
            expect(overrideRegistry.get('test-component')[1]).toBeInstanceOf(Object);
            expect(typeof overrideRegistry.get('test-component')[0].methods.testMethod).toBe('function');
            expect(typeof overrideRegistry.get('test-component')[1].methods.testMethod).toBe('function');
            expect(overrideRegistry.get('test-component')[0].methods.testMethod()).toBe('This is the second override.');
            expect(overrideRegistry.get('test-component')[1].methods.testMethod()).toBe('This is the first override.');
        }
    );

    it(
        'should provide the rendered template of a component including overrides',
        () => {
            ComponentFactory.register('test-component', {
                template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
            });

            const renderedTemplate = ComponentFactory.getComponentTemplate('test-component');

            ComponentFactory.override('test-component', {
                template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
            });

            const overriddenTemplate = ComponentFactory.getComponentTemplate('test-component');

            expect(renderedTemplate).toBe('<div>This is a test template.</div>');
            expect(overriddenTemplate).toBe('<div>This is a template override.</div>');
        }
    );

    it('should build the final component structure with extension', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.extend('test-component-extension', 'test-component', {
            methods: {
                testMethod() {
                    return 'This is an extension.';
                }
            },
            template: '{% block content %}<div>This is an extended template.</div>{% endblock %}'
        });

        const component = ComponentFactory.build('test-component');
        const extension = ComponentFactory.build('test-component-extension');

        expect(component).toBeInstanceOf(Object);
        expect(component.methods).toBeInstanceOf(Object);
        expect(typeof component.methods.testMethod).toBe('function');
        expect(component.methods.testMethod()).toBe('This is a test method.');
        expect(component.template).toBe('<div>This is a test template.</div>');

        expect(extension).toBeInstanceOf(Object);
        expect(extension.methods).toBeInstanceOf(Object);
        expect(typeof extension.methods.testMethod).toBe('function');
        expect(extension.methods.testMethod()).toBe('This is an extension.');
        expect(extension.template).toBe('<div>This is an extended template.</div>');

        expect(extension.extends).toBeInstanceOf(Object);
        expect(extension.extends.template).toBe(undefined);
        expect(extension.extends.methods).toBeInstanceOf(Object);
        expect(typeof extension.extends.methods.testMethod).toBe('function');
        expect(extension.extends.methods.testMethod()).toBe('This is a test method.');
    });

    it('should build the final component structure with an override', () => {
        ComponentFactory.register('test-component', {
            created() {},
            methods: {
                testMethod() {
                    return 'This is a test method.';
                }
            },
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        ComponentFactory.override('test-component', {
            methods: {
                testMethod() {
                    return 'This is an override.';
                }
            },
            template: '{% block content %}<div>This is an override of a template.</div>{% endblock %}'
        });

        const component = ComponentFactory.build('test-component');

        expect(component).toBeInstanceOf(Object);
        expect(component.methods).toBeInstanceOf(Object);
        expect(typeof component.methods.testMethod).toBe('function');
        expect(component.methods.testMethod()).toBe('This is an override.');
        expect(component.template).toBe('<div>This is an override of a template.</div>');

        expect(component.extends).toBeInstanceOf(Object);
        expect(component.extends.template).toBe(undefined);
        expect(component.extends.methods).toBeInstanceOf(Object);
        expect(typeof component.extends.methods.testMethod).toBe('function');
        expect(component.extends.methods.testMethod()).toBe('This is a test method.');
    });
});
