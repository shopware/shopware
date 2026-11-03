import { routeForSpec } from '../fake-index';
import { rewriteService } from './service';

describe('rewriteService', () => {
    it('looks a service up through a function in dependencies.ts with the constant of its name', () => {
        // ARRANGE
        const source = `export default {
    created() {
        const service = Shopware.Service('shopwareServicesService');
        Shopware.Service('repositoryFactory').create('product');
    },
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { use: [rewriteService] });

        // ASSERT
        expect(code).toContain('const service = getShopwareServicesService();');
        expect(code).toContain("getRepositoryFactory().create('product');");
        expect(dependencies).toBe(`import { repositoryFactory, shopwareServicesService } from 'src/app/service-names';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getShopwareServicesService = () => Shopware.Service(shopwareServicesService);
`);
        expect(outsideEdits).toEqual([{ kind: 'service-name', name: 'shopwareServicesService' }]);
    });

    it('leaves the service container and a service looked up by a variable', () => {
        // ARRANGE
        const source = "Shopware.Service().get('acl');\nShopware.Service(name);\n";

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteService] });

        // ASSERT
        expect(code).toBe(source);
    });
});
