import ComponentFactory from 'src/core/factory/component.factory';

describe('core/factories/component.factory.js', () => {
    it('should create a component', () => {
        const compDefinition = {
            template: '<div></div>'
        };
        const comp = ComponentFactory.createComponent('test-component', compDefinition);

        expect(comp).to.be.an('function');
    });

    it('should register a component in the registry', () => {
        const compDefinition = {
            template: '<div></div>'
        };
        ComponentFactory.createComponent('test-component', compDefinition);
        const registry = ComponentFactory.getComponentRegistry();

        expect(registry.has('test-component')).is.equal(true);
        expect(registry.get('test-component')).to.be.an('object');
    });

    it('should be able to extend the functionality', () => {
        ComponentFactory.extendComponentLogic('test-component', {
            methods: {
                onButtonClick() {}
            }
        });

        const compDefinition = {
            methods: {
                onSomething() {}
            },
            template: '<div></div>'
        };
        ComponentFactory.createComponent('test-component', compDefinition);
        const registry = ComponentFactory.getComponentRegistry();

        expect(registry.get('test-component').methods.onSomething).to.be.a('function');
        expect(registry.get('test-component').methods.onButtonClick).to.be.a('function');
    });
});
