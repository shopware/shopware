/**
 * @package admin
 */
describe('directives/click-outside', () => {
    it('should register the directive', () => {
        expect(Shopware.Directive.getByName('click-outside')).toBeDefined();
    });
});
