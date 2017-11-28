/* eslint-disable import/no-named-as-default-member */
import ComponentFactory from 'src/core/factory/component.factory';
import TemplateFactory from 'src/core/factory/template.factory';

beforeEach(() => {
    ComponentFactory.getComponentRegistry().clear();
    ComponentFactory.getOverrideRegistry().clear();
    TemplateFactory.getTemplateRegistry().clear();
    TemplateFactory.disableTwigCache();
});

describe('core/factory/component.factory.js', () => {
    it('should register a component and it should be registered in the component registry', () => {
        const component = ComponentFactory.register('test-component', {
            template: '<div>This is a test template.</div>'
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(component).to.be.an('object');
        expect(registry.has('test-component')).is.equal(true);
        expect(registry.get('test-component')).to.be.an('object');
    });

    it('should not be possible to register a component with the same name twice', () => {
        const compDefinition = {
            template: '<div>This is a test template.</div>'
        };

        ComponentFactory.register('test-component', compDefinition);
        const component = ComponentFactory.register('test-component', compDefinition);

        expect(component).is.equal(false);
    });

    it('should not be possible to register a component without a name', () => {
        const component = ComponentFactory.register('', {
            template: '<div>This is a test template.</div>'
        });

        expect(component).is.equal(false);
    });

    it('should not be possible to register a component without a template', () => {
        const component = ComponentFactory.register('test-component', {});

        expect(component).is.equal(false);
    });

    it('should not have a template property after registering a component', () => {
        const component = ComponentFactory.register('test-component', {
            template: '<div>This is a test template.</div>'
        });

        expect(component.template).is.equal(undefined);
    });

    it('should extend a given component & should register a new component (without template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {}
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(extension.updated).is.a('function');
        expect(extension.extends).to.be.an('String');
        expect(extension.extends).is.equal('test-component');
        expect(registry.has('test-component-extension')).is.equal(true);
        expect(registry.get('test-component-extension')).to.be.an('object');
    });

    it('should extend a given component & should register a new component (with template)', () => {
        ComponentFactory.register('test-component', {
            created() {},
            template: '<div>This is a test template.</div>'
        });

        const extension = ComponentFactory.extend('test-component-extension', 'test-component', {
            updated() {},
            template: '<div>This is an extension.</div>'
        });

        const registry = ComponentFactory.getComponentRegistry();

        expect(extension.updated).is.a('function');
        expect(extension.extends).to.be.an('String');
        expect(extension.extends).is.equal('test-component');
        expect(registry.has('test-component-extension')).is.equal(true);
        expect(registry.get('test-component-extension')).to.be.an('object');
        expect(extension.template).is.equal(undefined);
    });

    it('should register an override of an existing component in the override registry (without index)', () => {
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

        expect(override.methods.testMethod).is.a('function');
        expect(override.template).is.equal(undefined);
        expect(registry.has('test-component')).is.equal(true);
        expect(registry.get('test-component')).to.be.an('object');
        expect(overrideRegistry.has('test-component')).is.equal(true);
        expect(overrideRegistry.get('test-component')).to.be.an('Array');
        expect(overrideRegistry.get('test-component').length).is.equal(1);
        expect(overrideRegistry.get('test-component')[0]).to.be.an('Object');
    });

    it('should register two overrides of an existing component in the override registry (with index)', () => {
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

        expect(overrideOne.methods.testMethod).is.a('function');
        expect(overrideTwo.methods.testMethod).is.a('function');
        expect(overrideOne.template).is.equal(undefined);
        expect(overrideTwo.template).is.equal(undefined);
        expect(registry.has('test-component')).is.equal(true);
        expect(registry.get('test-component')).to.be.an('Object');
        expect(overrideRegistry.has('test-component')).is.equal(true);
        expect(overrideRegistry.get('test-component')).to.be.an('Array');
        expect(overrideRegistry.get('test-component').length).is.equal(2);
        expect(overrideRegistry.get('test-component')[0]).to.be.an('Object');
        expect(overrideRegistry.get('test-component')[1]).to.be.an('Object');
        expect(overrideRegistry.get('test-component')[0].methods.testMethod).to.be.a('function');
        expect(overrideRegistry.get('test-component')[1].methods.testMethod).to.be.a('function');
        expect(overrideRegistry.get('test-component')[0].methods.testMethod()).is.equal('This is the second override.');
        expect(overrideRegistry.get('test-component')[1].methods.testMethod()).is.equal('This is the first override.');
    });

    it('should provide the rendered template of a component including overrides', () => {
        ComponentFactory.register('test-component', {
            template: '{% block content %}<div>This is a test template.</div>{% endblock %}'
        });

        const renderedTemplate = ComponentFactory.getComponentTemplate('test-component');

        ComponentFactory.override('test-component', {
            template: '{% block content %}<div>This is a template override.</div>{% endblock %}'
        });

        const overriddenTemplate = ComponentFactory.getComponentTemplate('test-component');

        expect(renderedTemplate).to.be.equal('<div>This is a test template.</div>');
        expect(overriddenTemplate).to.be.equal('<div>This is a template override.</div>');
    });

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

        expect(component).to.be.an('Object');
        expect(component.methods).to.be.an('Object');
        expect(component.methods.testMethod).to.be.a('function');
        expect(component.methods.testMethod()).to.be.equal('This is a test method.');
        expect(component.template).to.be.equal('<div>This is a test template.</div>');

        expect(extension).to.be.an('Object');
        expect(extension.methods).to.be.an('Object');
        expect(extension.methods.testMethod).to.be.a('function');
        expect(extension.methods.testMethod()).to.be.equal('This is an extension.');
        expect(extension.template).to.be.equal('<div>This is an extended template.</div>');

        expect(extension.extends).to.be.an('Object');
        expect(extension.extends.template).is.equal(undefined);
        expect(extension.extends.methods).to.be.an('Object');
        expect(extension.extends.methods.testMethod).to.be.a('function');
        expect(extension.extends.methods.testMethod()).to.be.equal('This is a test method.');
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

        expect(component).to.be.an('Object');
        expect(component.methods).to.be.an('Object');
        expect(component.methods.testMethod).to.be.a('function');
        expect(component.methods.testMethod()).to.be.equal('This is an override.');
        expect(component.template).to.be.equal('<div>This is an override of a template.</div>');

        expect(component.extends).to.be.an('Object');
        expect(component.extends.template).is.equal(undefined);
        expect(component.extends.methods).to.be.an('Object');
        expect(component.extends.methods.testMethod).to.be.a('function');
        expect(component.extends.methods.testMethod()).to.be.equal('This is a test method.');
    });
});
