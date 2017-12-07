describe('core/common.js', () => {
    it('should contain the necessary methods for the module factory', () => {
        expect(Shopware.Module).to.have.property('register');
    });

    it('should contain the necessary methods for the component factory', () => {
        expect(Shopware.Component).to.have.property('register');
        expect(Shopware.Component).to.have.property('extend');
        expect(Shopware.Component).to.have.property('override');
        expect(Shopware.Component).to.have.property('build');
        expect(Shopware.Component).to.have.property('getTemplate');
    });

    it('should contain the necessary methods for the template factory', () => {
        expect(Shopware.Template).to.have.property('register');
        expect(Shopware.Template).to.have.property('extend');
        expect(Shopware.Template).to.have.property('override');
        expect(Shopware.Template).to.have.property('getRenderedTemplate');
        expect(Shopware.Template).to.have.property('find');
        expect(Shopware.Template).to.have.property('findOverride');
    });

    it('should contain the utility collection & application', () => {
        expect(Shopware).to.have.property('Utils');
        expect(Shopware).to.have.property('Application');
    });
});
