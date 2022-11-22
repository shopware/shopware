import StringHelper from 'src/helper/string.helper';

/**
 * @package storefront
 */
describe('string-helper', () => {
    test('ucFirst changes first char to upper case', () => {
        expect(StringHelper.ucFirst('i once was little')).toBe('I once was little');
        expect(StringHelper.ucFirst('0 has no upper case')).toBe('0 has no upper case');
        expect(StringHelper.ucFirst('I was always upper case')).toBe('I was always upper case');
        expect(StringHelper.ucFirst('_ was always upper case')).toBe('_ was always upper case');

    });

    test('ucFirst with empty string returns empty string', () => {
        expect(StringHelper.ucFirst('')).toBe('');
    });

    test('ucFirst to throw on null and undefined', () => {
        expect(() => { StringHelper.ucFirst(null) }).toThrow();
        expect(() => { StringHelper.ucFirst(undefined) }).toThrow();
    });

    test('lcFirst changes first char to upper case', () => {
        expect(StringHelper.lcFirst('i once was little')).toBe('i once was little');
        expect(StringHelper.lcFirst('0 has no upper case')).toBe('0 has no upper case');
        expect(StringHelper.lcFirst('I was always upper case')).toBe('i was always upper case');
        expect(StringHelper.lcFirst('_ was always upper case')).toBe('_ was always upper case');

    });

    test('lcFirst with empty string returns empty string', () => {
        expect(StringHelper.lcFirst('')).toBe('');
    });

    test('lcFirst to throw on null and undefined', () => {
        expect(() => { StringHelper.lcFirst(null) }).toThrow();
        expect(() => { StringHelper.lcFirst(undefined) }).toThrow();
    });

    test('toDashCase converts camel case', () => {
        expect(StringHelper.toDashCase('thisIsACamelCaseString')).toBe('this-is-a-camel-case-string');
        expect(StringHelper.toDashCase('ThisIsACamelCaseString')).toBe('this-is-a-camel-case-string');
    });

    test('toUpperCamelCase without seperator is just ucFirst', () => {
        expect(StringHelper.toUpperCamelCase('this-is-a-camel-case-string')).toBe('This-is-a-camel-case-string');
    });

    test('toLowerCamelCase and toUpperCamelcase converting from dash case', () => {
        expect(StringHelper.toLowerCamelCase('this-is-a-camel-case-string', '-')).toBe('thisIsACamelCaseString');
        expect(StringHelper.toUpperCamelCase('this-is-a-camel-case-string', '-')).toBe('ThisIsACamelCaseString');
    });

    test('toLowerCamelCase and toUpperCamelcase from snake case', () => {
        expect(StringHelper.toLowerCamelCase('this_is_a_camel_case_string', '_')).toBe('thisIsACamelCaseString');
        expect(StringHelper.toUpperCamelCase('this_is_a_camel_case_string','_')).toBe('ThisIsACamelCaseString');
    });

    test('parse primitives returns number', () => {
        let number = StringHelper.parsePrimitive('1.3');
        expect(number).toStrictEqual(1.3);

        number = StringHelper.parsePrimitive('1,3');
        expect(number).toStrictEqual(1.3);

        number = StringHelper.parsePrimitive('13');
        expect(number).toStrictEqual(13);
    });

    test('parse primitives can parse objects', () => {
        const example = {
            n: 2.33334,
            arr: [2, 3, 4],
            str: 'this is string',
        };

        expect(StringHelper.parsePrimitive(JSON.stringify(example))).toEqual(example);
    });

    test('parse primitives returns string value for strings', () => {
        expect(StringHelper.parsePrimitive('everybody is kung-fu fighting')).toEqual('everybody is kung-fu fighting');
    });

    test('parse primitives returns string for malformed json', () => {
        const example = {
            n: 2.33334,
            arr: [2, 3, 4],
            str: 'this is string',
        };

        const malformed = JSON.stringify(example).replace(/"/g, '');
        expect(StringHelper.parsePrimitive(malformed)).toBe(malformed);
    })
});
