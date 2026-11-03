import { readDeclarationKind, readExtendShape } from './inherit-privacy';

describe('readDeclarationKind', () => {
    it('finds the declaration of a public module and of a registered private one', () => {
        // ARRANGE
        const files: [string, string, boolean][] = [
            ['src/a/sw-a/index.ts', "export default definePublicComponent({ name: 'sw-a' });", true],
            ['src/a/sw-b/private.ts', "export default definePrivateComponent({ name: 'sw-b' });", true],
            ['src/a/sw-page/src/sw-card/private.ts', "export default definePrivateComponent({ name: 'sw-card' });", false],
            ['src/a/sw-c/index.ts', "export default { name: 'sw-c' };", true],
        ];

        // ACT
        const kinds = files.map(([file, source, hasRegister]) => readDeclarationKind(file, source, hasRegister));

        // ASSERT
        expect(kinds).toEqual([
            'public',
            'private',
            null,
            null,
        ]);
    });
});

describe('readExtendShape', () => {
    it('makes a component that inherits from a private one private too', () => {
        // ARRANGE
        const componentSource = '/**\n * @sw-package framework\n */\nexport default {};\n';

        // ACT
        const shape = readExtendShape({
            base: 'sw-text-field-deprecated',
            isRegistrationPrivate: false,
            componentSource,
            privateBase: 'sw-text-field',
        });

        // ASSERT
        expect(shape).toEqual({
            kind: 'extend',
            base: 'sw-text-field-deprecated',
            isPrivate: true,
            privateBase: 'sw-text-field',
        });
    });

    it('keeps a component public that says @public, and one whose bases are all public', () => {
        // ARRANGE
        const marked = '/**\n * @public\n */\nexport default {};\n';
        const unmarked = 'export default {};\n';

        // ACT
        const shapes = [
            readExtendShape({
                base: 'sw-condition-base',
                isRegistrationPrivate: false,
                componentSource: marked,
                privateBase: 'sw-condition-base',
            }),
            readExtendShape({ base: 'sw-card', isRegistrationPrivate: false, componentSource: unmarked, privateBase: null }),
        ];

        // ASSERT
        expect(shapes).toEqual([
            { kind: 'extend', base: 'sw-condition-base', isPrivate: false, privateBase: 'sw-condition-base' },
            { kind: 'extend', base: 'sw-card', isPrivate: false },
        ]);
    });
});
