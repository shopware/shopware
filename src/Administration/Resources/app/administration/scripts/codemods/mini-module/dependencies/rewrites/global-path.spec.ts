import { routeForSpec } from '../fake-index';
import { rewriteGlobalPath } from './global-path';

describe('rewriteGlobalPath', () => {
    it('imports data classes and objects from their modules, calls functions through the global, and removes the destructure', () => {
        // ARRANGE
        const source = `const { Criteria } = Shopware.Data;
const { format } = Shopware.Utils;

export default {
    methods: {
        load() {
            const criteria = new Criteria(1, 25);
            const id = Shopware.Utils.createId();
            return [criteria, id, format.fileSize(1), new Shopware.Data.Criteria()];
        },
    },
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteGlobalPath] });

        // ASSERT
        expect(code).toBe(`import { Criteria, fileSize, Utils } from '../dependencies';

export default {
    methods: {
        load() {
            const criteria = new Criteria(1, 25);
            const id = Utils.createId();
            return [criteria, id, fileSize(1), new Criteria()];
        },
    },
};
`);
        expect(dependencies).toBe(
            `/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';

/** @private */
export const fileSize = (...args: Parameters<typeof Shopware.Utils.format.fileSize>) => Shopware.Utils.format.fileSize(...args);
`,
        );
    });

    it('leaves a path when the import would clash with a name the file declares, and keeps a written global', () => {
        // ARRANGE
        const source = `const Criteria = 1;
const criteria = new Shopware.Data.Criteria();
Shopware.Utils.createId = () => 'id';
`;

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteGlobalPath] });

        // ASSERT
        expect(code).toBe(`import { Utils } from '../dependencies';

const Criteria = 1;
const criteria = new Shopware.Data.Criteria();
Utils.createId = () => 'id';
`);
    });

    it('calls a package value through the global, imports it for new, and leaves any other use', () => {
        // ARRANGE
        const source = `const search = Shopware.Utils.debounce(() => {}, 300);
const criteria = new Shopware.Data.Criteria();
const Klass = Shopware.Data.Criteria;
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteGlobalPath] });

        // ASSERT
        expect(code).toBe(`import { Criteria, debounce } from '../dependencies';

const search = debounce(() => {}, 300);
const criteria = new Criteria();
const Klass = Shopware.Data.Criteria;
`);
        expect(dependencies).toContain(
            'export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);',
        );
    });

    it('leaves registries and getters of the global, such as Snippet and Module', () => {
        // ARRANGE
        const source = "Shopware.Module.register('x', {});\nconst t = Shopware.Snippet.t('y');\n";

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteGlobalPath] });

        // ASSERT
        expect(code).toBe(source);
    });
});
