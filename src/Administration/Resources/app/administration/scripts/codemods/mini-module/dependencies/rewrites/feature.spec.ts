import { routeForSpec } from '../fake-index';
import { rewriteFeature } from './feature';

describe('rewriteFeature', () => {
    it('asks for a feature flag through a function in dependencies.ts', () => {
        // ARRANGE
        const source = "const isActive = Shopware.Feature.isActive('V6_8_0_0');\n";

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteFeature] });

        // ASSERT
        expect(code).toBe(
            "import { isFeatureActive } from '../dependencies';\n\nconst isActive = isFeatureActive('V6_8_0_0');\n",
        );
        expect(dependencies).toBe(
            '/** @private */\nexport const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);\n',
        );
    });

    it('leaves the other members of Feature', () => {
        // ARRANGE
        const source = 'const flags = Shopware.Feature.getAll();\n';

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteFeature] });

        // ASSERT
        expect(code).toBe(source);
    });
});
