import { quoteJsString } from './string-literals';

/**
 * The generated `.vue` files are parsed again — by the build transform, by
 * prettier in the CLI, and by the compiler. So the only thing that matters about
 * a quoted key is that it parses back into exactly the value it was made from.
 */
describe('scripts/codemods/sfc-migration/string-literals', () => {
    it.each([
        [
            'a plain key',
            'repositoryFactory',
            "'repositoryFactory'",
        ],
        [
            'a single quote',
            "it's",
            "'it\\'s'",
        ],
        [
            'a backslash',
            'a\\b',
            "'a\\\\b'",
        ],
        [
            'a line break',
            'a\nb',
            "'a\\u000ab'",
        ],
        [
            'a carriage return',
            'a\rb',
            "'a\\u000db'",
        ],
        [
            'a tab',
            'a\tb',
            "'a\\u0009b'",
        ],
        [
            'a line separator',
            `a${String.fromCharCode(0x2028)}b`,
            "'a\\u2028b'",
        ],
        [
            'a paragraph separator',
            `a${String.fromCharCode(0x2029)}b`,
            "'a\\u2029b'",
        ],
    ])('quotes %s into a literal that parses back to the original value', (_name, value, expected) => {
        const quoted = quoteJsString(value);

        expect(quoted).toBe(expected);
        expect(new Function(`return ${quoted};`)()).toBe(value);
    });
});
