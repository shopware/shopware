import { findImports, findRegistration } from './find-registration';

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
