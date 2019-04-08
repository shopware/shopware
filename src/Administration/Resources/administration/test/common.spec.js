import { Module, Component, Template } from 'src/core/shopware';

describe('core/common.js', () => {
    it('should contain the necessary methods for the module factory', () => {
        expect(Module).toHaveProperty('register');
    });

    it('should contain the necessary methods for the component factory', () => {
        expect(Component).toHaveProperty('register');
        expect(Component).toHaveProperty('extend');
        expect(Component).toHaveProperty('override');
        expect(Component).toHaveProperty('build');
        expect(Component).toHaveProperty('getTemplate');
    });

    it('should contain the necessary methods for the template factory', () => {
        expect(Template).toHaveProperty('register');
        expect(Template).toHaveProperty('extend');
        expect(Template).toHaveProperty('override');
        expect(Template).toHaveProperty('getRenderedTemplate');
        expect(Template).toHaveProperty('find');
        expect(Template).toHaveProperty('findOverride');
    });
});
