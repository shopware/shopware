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

    it('reads the names a destructure keeps from an import of the same object', () => {
        // ARRANGE
        const source = 'const { createId, debug } = Shopware.Utils;\nexport const id = () => [createId(), debug];\n';

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: USE_REWRITES });

        // ASSERT
        expect(code).toBe(
            "import { Utils } from '../dependencies';\n\nconst { createId, debug } = Utils;\nexport const id = () => [createId(), debug];\n",
        );
        expect(dependencies).toBe("/** @private */\nexport { default as Utils } from 'src/core/service/util.service';\n");
    });

    it('keeps the key of a shorthand property whose value it replaces', () => {
        // ARRANGE
        const source = 'const { Utils: U } = Shopware;\nexport default { data() { return { U }; } };\n';

        // ACT
        const { code } = routeForSpec(source, { use: USE_REWRITES });

        // ASSERT
        expect(code).toBe(
            "import { Utils } from '../dependencies';\n\nexport default { data() { return { U: Utils }; } };\n",
        );
    });

    it('adds the import below a file header, but above the doc comment of the first statement', () => {
        // ARRANGE
        const component = "export default { mixins: [Shopware.Mixin.getByName('notification')] };\n";
        const sources = [
            `/**\n * @sw-package checkout\n */\n\n${component}`,
            `/**\n * @private\n */\n${component}`,
        ];

        // ACT
        const codes = sources.map((source) => routeForSpec(source, { use: USE_REWRITES }).code);

        // ASSERT
        expect(codes).toEqual([
            "/**\n * @sw-package checkout\n */\nimport { notificationMixin } from '../dependencies';\n\nexport default { mixins: [notificationMixin] };\n",
            "import { notificationMixin } from '../dependencies';\n\n/**\n * @private\n */\nexport default { mixins: [notificationMixin] };\n",
        ]);
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
