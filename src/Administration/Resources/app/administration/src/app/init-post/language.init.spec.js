/**
 * @sw-package framework
 */
import initLanguageService from 'src/app/init-post/language.init';

describe('src/app/init-post/language.init.ts', () => {
    afterEach(() => {
        Shopware.Store.get('session').removeCurrentUser();
    });

    it('should init the language service', () => {
        const mock = jest.fn(() => null);
        Shopware.Application.$container.resetProviders();
        Shopware.Store.get('session').setCurrentUser({
            admin: false,
            aclRoles: [
                {
                    privileges: ['language:read'],
                },
            ],
        });

        Shopware.Service().register('languageAutoFetchingService', mock);

        initLanguageService();

        // middleware should not be executed yet
        expect(mock).not.toHaveBeenCalled();

        // access repositoryFactory to trigger the middleware
        Shopware.Application.getContainer('service').repositoryFactory.create('product');

        // middleware should be executed now
        expect(mock).toHaveBeenCalled();
    });

    it('should not init the language service without language read permissions', () => {
        const mock = jest.fn(() => null);
        Shopware.Application.$container.resetProviders();
        Shopware.Store.get('session').setCurrentUser({
            admin: false,
            aclRoles: [],
        });

        Shopware.Service().register('languageAutoFetchingService', mock);

        initLanguageService();

        // access repositoryFactory to trigger the middleware
        Shopware.Application.getContainer('service').repositoryFactory.create('product');

        expect(mock).not.toHaveBeenCalled();
    });
});
