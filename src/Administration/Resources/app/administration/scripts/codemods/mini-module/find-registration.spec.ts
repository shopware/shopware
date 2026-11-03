import { findImports, findRegistration, findRegistrationByPath } from './find-registration';

describe('findRegistration', () => {
    it('finds a registration on one line', () => {
        // ARRANGE
        const source =
            "import './acl';\nShopware.Component.register('sw-a', () => import('./component/sw-a'));\nconst x = 1;\n";

        // ACT
        const registration = findRegistration(source, 'sw-a');

        // ASSERT
        expect(registration).toEqual({
            kind: 'register',
            specifier: './component/sw-a',
            isPrivate: false,
            start: 16,
            end: 87,
        });
        expect(source.slice(0, registration?.start) + source.slice(registration?.end)).toBe(
            "import './acl';\nconst x = 1;\n",
        );
    });

    it('finds a registration spread over several lines, with its doc comment', () => {
        // ARRANGE
        const source = `/**
 * @private
 */
Shopware.Component.register(
    'sw-bulk-edit-product-visibility',
    () => import('./component/product/sw-bulk-edit-product-visibility'),
);
Shopware.Component.register('sw-other', () => import('./component/sw-other'));
`;

        // ACT
        const registration = findRegistration(source, 'sw-bulk-edit-product-visibility');

        // ASSERT
        expect(registration).toMatchObject({
            kind: 'register',
            specifier: './component/product/sw-bulk-edit-product-visibility',
            isPrivate: true,
        });
        expect(source.slice(0, registration?.start) + source.slice(registration?.end)).toBe(
            "Shopware.Component.register('sw-other', () => import('./component/sw-other'));\n",
        );
    });

    it('takes the comments right above a registration with it, but not a file header or an eslint-disable block', () => {
        // ARRANGE
        const source = `/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-a', () => import('./sw-a'));
/*
 * @private
 * @sw-package discovery
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-b', () => import('./sw-b'));
`;

        // ACT
        const registrations = ['sw-a', 'sw-b'].map((name) => findRegistration(source, name));

        // ASSERT
        expect(registrations.map((found) => source.slice(found?.start, found?.end))).toEqual([
            "Shopware.Component.register('sw-a', () => import('./sw-a'));\n",
            `/*
 * @private
 * @sw-package discovery
 */
/* eslint-disable-next-line sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-b', () => import('./sw-b'));
`,
        ]);
        expect(registrations.map((found) => found?.isPrivate)).toEqual([
            false,
            true,
        ]);
    });

    it('finds an extension and the component it extends', () => {
        // ARRANGE
        const source = `Component.extend(
    'sw-settings-logging-mail-sent-info',
    'sw-settings-logging-entry-info',
    () => import('./component/sw-settings-logging-mail-sent-info'),
);
`;

        // ACT
        const registration = findRegistration(source, 'sw-settings-logging-mail-sent-info');

        // ASSERT
        expect(registration).toEqual({
            kind: 'extend',
            base: 'sw-settings-logging-entry-info',
            specifier: './component/sw-settings-logging-mail-sent-info',
            isPrivate: false,
            start: 0,
            end: source.length,
        });
    });

    it('finds a registration inside a function', () => {
        // ARRANGE
        const source = `export default () => {
    Shopware.Component.register('sw-collapse', () => import('src/app/component/base/sw-collapse/index'));
};
`;

        // ACT
        const registration = findRegistration(source, 'sw-collapse');

        // ASSERT
        expect(registration).toMatchObject({ kind: 'register', specifier: 'src/app/component/base/sw-collapse/index' });
        expect(source.slice(0, registration?.start) + source.slice(registration?.end)).toBe('export default () => {\n};\n');
    });

    it('ignores registrations of other names, without a lazy import, and mentions of the name', () => {
        // ARRANGE
        const source = `
Shopware.Component.register('sw-a-b', () => import('./sw-a-b'));
Shopware.Component.register('sw-a', { template: '' });
Shopware.Component.override('sw-a', () => import('./sw-a-override'));
const name = 'sw-a';
`;

        // ACT
        const registration = findRegistration(source, 'sw-a');

        // ASSERT
        expect(registration).toBeNull();
    });
});

describe('findImports', () => {
    it('finds the imports whose path matches, with the range of the path and its line', () => {
        // ARRANGE
        const source = `import A from './a';
import Card from '../../component/sw-card';
`;

        // ACT
        const imports = findImports(source, (specifier) => specifier.endsWith('/sw-card'));

        // ASSERT
        expect(imports).toEqual([{ specifier: '../../component/sw-card', start: 39, end: 62, lineStart: 21 }]);
        expect(source.slice(imports[0].start, imports[0].end)).toBe('../../component/sw-card');
    });
});

describe('findRegistrationByPath', () => {
    it('finds the name a directory not named after its component is registered under', () => {
        // ARRANGE
        const source = `Shopware.Component.register('sw-cms-el-preview-text', () => import('./preview'));
Shopware.Component.register('sw-cms-el-text', () => import('./component'));
`;

        // ACT
        const found = findRegistrationByPath(source, (specifier) => specifier === './component');

        // ASSERT
        expect(found?.name).toBe('sw-cms-el-text');
        expect(found?.registration).toMatchObject({ kind: 'register', specifier: './component', isPrivate: false });
    });

    it('finds the name a component is registered under when it differs from its directory', () => {
        // ARRANGE
        const source = `Shopware.Component.register('sw-help-sidebar', () => import('src/app/component/sidebar/sw-help-sidebar'));
Shopware.Component.register('sw-help-center-v2', () => import('src/app/component/utils/sw-help-center'));
`;

        // ACT
        const found = findRegistrationByPath(source, (specifier) => specifier === 'src/app/component/utils/sw-help-center');

        // ASSERT
        expect(found?.name).toBe('sw-help-center-v2');
    });
});

describe('findImports', () => {
    it('finds a re-export in the dependencies.ts of a migrated parent', () => {
        // ARRANGE
        const source = "/** @private */\nexport { default as SwHero } from '../../component/sw-hero';\n";

        // ACT
        const imports = findImports(source, (specifier) => specifier.endsWith('sw-hero'));

        // ASSERT
        expect(imports.map(({ specifier }) => specifier)).toEqual(['../../component/sw-hero']);
    });
});
