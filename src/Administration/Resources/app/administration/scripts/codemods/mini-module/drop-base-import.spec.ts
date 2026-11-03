import { dropBaseImport } from './drop-base-import';

describe('dropBaseImport', () => {
    it('removes the bare import of the extended component', () => {
        // ARRANGE
        const source = "import 'src/app/component/base/sw-collapse';\nimport template from './x.html.twig';\n";

        // ACT
        const result = dropBaseImport(source, 'sw-collapse');

        // ASSERT
        expect(result).toBe("import template from './x.html.twig';\n");
    });

    it('keeps imports of other components and imports that bind a name', () => {
        // ARRANGE
        const source = [
            "import 'src/app/component/base/sw-collapse-extra';",
            "import SwCollapse from 'src/app/component/base/sw-collapse';",
            '',
        ].join('\n');

        // ACT
        const result = dropBaseImport(source, 'sw-collapse');

        // ASSERT
        expect(result).toBe(source);
    });
});
