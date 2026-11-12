/**
 * Reuses the Administration ESLint flat config for Storefront administration sources.
 *
 * Storefront administration tests are linted from the same rule entrypoint so shared aliases,
 * parser options, and Shopware rule compatibility stay aligned.
 */
import adminConfig from '../../../../Administration/Resources/app/administration/eslint.config.ts';

export default [
    ...adminConfig,
    // Test annotations cover the Administration's own specs only, see its technical-docs/11-code-quality/03-test-annotations.md
    {
        rules: {
            'sw-test-rules/test-annotation': 'off',
            'sw-test-rules/test-annotation-see-exception': 'off',
        },
    },
];
