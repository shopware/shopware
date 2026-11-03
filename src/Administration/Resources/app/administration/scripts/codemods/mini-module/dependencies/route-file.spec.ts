import { routeForSpec } from './fake-index';
import { USE_REWRITES, FILE_REWRITES } from './route-module';

describe('routeFile', () => {
    it('removes a destructure of the global only when every name of it was rewritten', () => {
        // ARRANGE
        const source = `const { Mixin, Module, Data } = Shopware;
const { Criteria } = Data;

export default {
    mixins: [Mixin.getByName('notification')],
    created() {
        Module.register('x', {});
        return new Criteria();
    },
};
`;

        // ACT
        const { code } = routeForSpec(source, { use: USE_REWRITES, file: FILE_REWRITES });

        // ASSERT
        expect(code).toBe(`import { Criteria, notificationMixin } from '../dependencies';

const { Module } = Shopware;

export default {
    mixins: [notificationMixin],
    created() {
        Module.register('x', {});
        return new Criteria();
    },
};
`);
    });

    it('keeps the key of a shorthand property whose value it replaces', () => {
        // ARRANGE
        const source = 'const { Criteria: C } = Shopware.Data;\nexport default { data() { return { C }; } };\n';

        // ACT
        const { code } = routeForSpec(source, { use: USE_REWRITES });

        // ASSERT
        expect(code).toBe(
            "import { Criteria } from '../dependencies';\n\nexport default { data() { return { C: Criteria }; } };\n",
        );
    });

    it('leaves a file that declares a Shopware of its own', () => {
        // ARRANGE
        const source = 'const Shopware = {};\nconst id = Shopware.Utils.createId();\n';

        // ACT
        const { code } = routeForSpec(source, { use: USE_REWRITES });

        // ASSERT
        expect(code).toBe(source);
    });
});
