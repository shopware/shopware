import { routeForSpec } from '../fake-index';
import { rewriteGlobalPath } from './global-path';

describe('rewriteGlobalPath', () => {
    it('imports data classes and utils from their modules, and removes the destructure', () => {
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
        expect(dependencies).toBe(`/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { fileSize } from 'src/core/service/utils/format.utils';
`);
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

    it('leaves registries and getters of the global, such as Snippet and Module', () => {
        // ARRANGE
        const source = "Shopware.Module.register('x', {});\nconst t = Shopware.Snippet.t('y');\n";

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteGlobalPath] });

        // ASSERT
        expect(code).toBe(source);
    });
});
