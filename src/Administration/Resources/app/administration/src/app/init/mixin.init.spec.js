import createAppMixin from 'src/app/init/mixin.init';

describe('src/app/init/mixin.init.js', () => {
    it('should register all app mixins', () => {
        createAppMixin();

        expect(Shopware.Mixin.getByName('discard-detail-page-changes')).toBeDefined();
        expect(Shopware.Mixin.getByName('sw-form-field')).toBeDefined();
        expect(Shopware.Mixin.getByName('generic-condition')).toBeDefined();
        expect(Shopware.Mixin.getByName('listing')).toBeDefined();
        expect(Shopware.Mixin.getByName('notification')).toBeDefined();
        expect(Shopware.Mixin.getByName('placeholder')).toBeDefined();
        expect(Shopware.Mixin.getByName('position')).toBeDefined();
        expect(Shopware.Mixin.getByName('remove-api-error')).toBeDefined();
        expect(Shopware.Mixin.getByName('ruleContainer')).toBeDefined();
        expect(Shopware.Mixin.getByName('salutation')).toBeDefined();
        expect(Shopware.Mixin.getByName('sw-inline-snippet')).toBeDefined();
        expect(Shopware.Mixin.getByName('user-settings')).toBeDefined();
        expect(Shopware.Mixin.getByName('validation')).toBeDefined();
    });
});
