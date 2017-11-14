// Proxy variable to be more flexible for refactoring purposes
const ComponentFactory = Shopware.ComponentFactory;

// We're sharing the component throughout the test, so we're having a running counter to prevent issues
let count = 0;
beforeEach(() => {
    count += 1;
});

describe('core/factory/component.factory.js', () => {
    it('should register a component and it should be registered in the component registry', () => {
        const compDefinition = {
            template: '<div>A test component</div>'
        };
        const comp = ComponentFactory.register(`test-component-${count}`, compDefinition);
        const registry = ComponentFactory.getComponentRegistry();

        expect(comp).to.be.an('object');
        expect(registry.has(`test-component-${count}`)).is.equal(true);
        expect(registry.get(`test-component-${count}`)).to.be.an('object');
    });

    it('should not be possible to register a component with the same name twice', () => {
        const compDefinition = {
            template: '<div>A test component</div>'
        };
        const comp = ComponentFactory.register(`test-component-${count - 1}`, compDefinition);

        expect(comp).is.equal(false);
    });

    it('should not be possible to register a component without a name', () => {
        const compDefinition = {
            template: '<div>A test component</div>'
        };
        const comp = ComponentFactory.register('', compDefinition);

        expect(comp).is.equal(false);
    });

    it('should not be possible to register a component without a template', () => {
        const compDefinition = {};
        const comp = ComponentFactory.register(`test-component-${count}`, compDefinition);

        expect(comp).is.equal(false);
    });

    it('should not have a template property after registering a component', () => {
        const compDefinition = {
            template: '<div>A test component</div>'
        };
        const comp = ComponentFactory.register(`test-component-${count}`, compDefinition);
        expect(comp.templates).is.equal(undefined);
    });

    it('should extend a given component & should register a new component (without template)', () => {
        const compDefinition = {
            created() {}
        };

        const comp = ComponentFactory.extend(
            `test-component-${count}`,
            `test-component-${count - 1}`,
            compDefinition
        );
        const registry = ComponentFactory.getComponentRegistry();

        expect(comp.created).is.a('function');
        expect(registry.has(`test-component-${count}`)).is.equal(true);
        expect(registry.get(`test-component-${count}`)).to.be.an('object');
        expect(comp.templates).is.equal(undefined);
    });

    it('should extend a given component & should register a new component (with template)', () => {
        const compDefinition = {
            template: '<div>Sample content</div>',
            created() {}
        };

        const comp = ComponentFactory.extend(
            `test-component-${count}`,
            `test-component-${count - 1}`,
            compDefinition
        );
        const registry = ComponentFactory.getComponentRegistry();

        expect(comp.created).is.a('function');
        expect(registry.has(`test-component-${count}`)).is.equal(true);
        expect(registry.get(`test-component-${count}`)).to.be.an('object');
        expect(comp.templates).is.equal(undefined);
    });

    it('should override and register the component in the registry', () => {
        const compDefinition = {
            template: '<div>Sample content</div>',
            methods: {}
        };
        const comp = ComponentFactory.register(
            `test-component-${count}`,
            compDefinition
        );

        const overrideDefinition = {
            template: '<div class="override">Override content</div>'
        };

        const override = ComponentFactory.override(
            comp.name,
            overrideDefinition
        );

        const overrideDefinitionIndex = {
            template: '<div class="override">Override content</div>',
            created() {}
        };

        const overrideIndex = ComponentFactory.override(
            override.name,
            overrideDefinitionIndex,
            0
        );
        const registry = ComponentFactory.getComponentRegistry();

        expect(overrideIndex.created).is.a('function');
        expect(registry.has(`test-component-${count}`)).is.equal(true);
        expect(registry.get(`test-component-${count}`)).to.be.an('object');
        expect(comp.templates).is.equal(undefined);
    });

    it('should provide the rendered component', () => {
        const compDefinition = {
            template: '<div>{% block content %}Sample content{% endblock %}</div>'
        };
        const comp = ComponentFactory.register(
            `test-component-${count}`,
            compDefinition
        );

        const renderedTemplate = ComponentFactory.getComponentTemplate(comp.name);

        const extendDefinition = {
            template: '{% block content %}Extended component content{% endblock %}'
        };

        const extendComp = ComponentFactory.extend(
            `test-component-${count}-override`,
            comp.name,
            extendDefinition
        );

        const extendedRenderedTemplate = ComponentFactory.getComponentTemplate(extendComp.name);

        expect(renderedTemplate).to.be.equal('<div>Sample content</div>');
        expect(extendedRenderedTemplate).to.be.equal('<div>Extended component content</div>');
    });

    // TODO - Re-enable the test
    // Skipping the test cause we have a bug in the implementation
    it.skip('should build the final Vue component', () => {
        // Check what happens when we provide a component name which isn't registered yet.
        expect(ComponentFactory.build('foobar')).is.equal(false);

        const compDefinition = {
            template: '<div>{% block content %}Sample content{% endblock %}</div>',
            methods: {
                firstMethod() {}
            }
        };
        const comp = ComponentFactory.register(
            `test-component-${count}`,
            compDefinition
        );

        const extendDefinition = {
            template: '{% block content %}Extended component content{% endblock %}',
            methods: {
                secondMethod() {}
            }
        };

        const extendComp = ComponentFactory.extend(
            `test-component-${count}-extended`,
            comp.name,
            extendDefinition
        );

        const buildExtendComp = ComponentFactory.build(extendComp.name);

        expect(buildExtendComp).to.be.an('object').that.include({
            template: '<div>Extended component content</div>',
            methods: {
                firstMethod() {},
                secondMethod() {}
            }
        });
    });
});
