/**
 * Reuses the Administration ESLint flat config for Storefront administration sources.
 *
 * Storefront administration tests are linted from the same rule entrypoint so shared aliases,
 * parser options, and Shopware rule compatibility stay aligned.
 */
export { default } from '../../../../Administration/Resources/app/administration/eslint.config.ts';
