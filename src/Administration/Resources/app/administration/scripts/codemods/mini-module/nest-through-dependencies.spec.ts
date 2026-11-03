import { dropComponentReexport, exportDefineAsyncComponent, mountNested } from './nest-through-dependencies';

describe('nest through dependencies', () => {
    it('removes the re-export of the nested component from the parent dependencies.ts', () => {
        // ARRANGE
        const code = `/** @private */
export { default as SwHero } from '../../component/sw-hero';
/** @private */
export { default as SwOther } from '../../component/sw-other';
`;

        // ACT
        const result = dropComponentReexport(code, (specifier) => specifier.endsWith('/sw-hero'));

        // ASSERT
        expect(result).toEqual({
            code: "/** @private */\nexport { default as SwOther } from '../../component/sw-other';\n",
            names: ['SwHero'],
        });
    });

    it('refuses a re-export of anything but the default export', () => {
        // ACT
        const result = dropComponentReexport("export { helper } from './sw-hero';\n", () => true);

        // ASSERT
        expect(result).toBeNull();
    });

    it('mounts the nested component lazily from its private.ts', () => {
        // ARRANGE
        const code = `import template from './x.html.twig';
import { aclService, SwHero } from '../dependencies';

export default {
    components: { SwHero, 'sw-alias': SwHero },
};
`;

        // ACT
        const result = mountNested(code, 'SwHero', './sw-hero/private');

        // ASSERT
        expect(result).toBe(`import template from './x.html.twig';
import { defineAsyncComponent, aclService } from '../dependencies';
import SwHero from './sw-hero/private';

export default {
    components: { SwHero: defineAsyncComponent(SwHero.load), 'sw-alias': defineAsyncComponent(SwHero.load) },
};
`);
    });

    it('refuses to mount a component the file uses outside components', () => {
        // ARRANGE
        const code =
            "import { SwHero } from '../dependencies';\nexport default { components: { SwHero }, data: () => ({ c: SwHero }) };\n";

        // ACT
        const result = mountNested(code, 'SwHero', './sw-hero/private');

        // ASSERT
        expect(result).toBeNull();
    });

    it('exports defineAsyncComponent from vue in dependencies.ts once', () => {
        // ARRANGE
        const withVue = "/** @private */\nexport { defineComponent as wrapComponentConfig } from 'vue';\n";
        const withoutVue = "/**\n * @sw-package framework\n */\n/** @private */\nexport { mapState } from 'pinia';\n";

        // ACT
        const results = [
            exportDefineAsyncComponent(withVue),
            exportDefineAsyncComponent(exportDefineAsyncComponent(withoutVue)),
        ];

        // ASSERT
        expect(results[0]).toBe(
            "/** @private */\nexport { defineAsyncComponent, defineComponent as wrapComponentConfig } from 'vue';\n",
        );
        expect(results[1]).toBe(
            "/**\n * @sw-package framework\n */\n/** @private */\nexport { defineAsyncComponent } from 'vue';\n/** @private */\nexport { mapState } from 'pinia';\n",
        );
    });
});
