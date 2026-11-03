import { routeForSpec } from '../fake-index';
import { rewriteWrapComponentConfig } from './wrap-component-config';

describe('rewriteWrapComponentConfig', () => {
    it("takes wrapComponentConfig from Vue's defineComponent", () => {
        // ARRANGE
        const source = `import template from './sw-x-page.html.twig';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
});
`;

        // ACT
        const { code, dependencies } = routeForSpec(
            source,
            { use: [rewriteWrapComponentConfig] },
            '/admin/src/module/sw-x/page/sw-x-page/src/sw-x-page.ts',
        );

        // ASSERT
        expect(code).toBe(`import template from './sw-x-page.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
    template,
});
`);
        expect(dependencies).toBe("/** @private */\nexport { defineComponent as wrapComponentConfig } from 'vue';\n");
    });
});
