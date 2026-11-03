import { routeForSpec } from '../fake-index';
import { rewriteInject } from './inject';

describe('rewriteInject', () => {
    it('injects services by the constants of their names, in the same order', () => {
        // ARRANGE
        const source = `export default {
    inject: ['repositoryFactory', 'acl', 'swProductDetailLoadAll'],
};
`;

        // ACT
        const { code, dependencies, outsideEdits } = routeForSpec(source, { file: [rewriteInject] });

        // ASSERT
        expect(code).toBe(`import { aclService, repositoryFactoryService } from '../dependencies';

export default {
    inject: [repositoryFactoryService, aclService, 'swProductDetailLoadAll'],
};
`);
        expect(dependencies).toBe(`/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
`);
        expect(outsideEdits).toEqual([{ kind: 'service-name', name: 'acl' }]);
    });

    it('imports a service name under <name>Name when the file declares the name already', () => {
        // ARRANGE
        const source = `export default {
    inject: ['shopwareServicesService'],
    methods: {
        load() {
            const shopwareServicesService = this.shopwareServicesService;
            return shopwareServicesService;
        },
    },
};
`;

        // ACT
        const { code } = routeForSpec(source, { file: [rewriteInject] });

        // ASSERT
        expect(code).toContain("import { shopwareServicesService as shopwareServicesServiceName } from '../dependencies';");
        expect(code).toContain('inject: [shopwareServicesServiceName],');
    });

    it('keeps a name that already ends with Service, and leaves the object form of inject', () => {
        // ARRANGE
        const source = `export default {
    inject: ['shopwareServicesService'],
    components: { inner: { inject: { acl: { from: 'acl' } } } },
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { file: [rewriteInject] });

        // ASSERT
        expect(code).toContain('inject: [shopwareServicesService],');
        expect(code).toContain("inject: { acl: { from: 'acl' } }");
        expect(dependencies).toContain("export { shopwareServicesService } from 'src/app/service-names';");
    });
});
