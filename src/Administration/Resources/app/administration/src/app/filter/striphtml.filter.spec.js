/**
 * @package admin
 */
describe('src/app/filter/striphtml.filter.ts', () => {
    const stripHtmlFilter = Shopware.Filter.getByName('striphtml');

    it('should contain a filter', () => {
        expect(stripHtmlFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(stripHtmlFilter()).toBe('');
    });

    [
        ['', ''],
        ['Hello, World!', 'Hello, World!'],
        ['foo&amp;bar', 'foo&amp;bar'],
        ['Hello <a href="www.example.com/">World</a>!', 'Hello World!'],
        ['Foo <textarea>Bar</textarea> Baz', 'Foo Bar Baz'],
        ['Foo <!-- Bar --> Baz', 'Foo  Baz'],
        ['<', ''],
        ['foo < bar', 'foo '],
        ['Foo<script type="text/javascript">alert(1337)</script>Bar', 'Fooalert(1337)Bar'],
        ['Foo<div title="1>2">Bar', 'FooBar'],
        ['I <3 Ponies!', 'I '],
        ['<script>foo()</script>', 'foo()'],
    ].forEach(([input, expected]) => {
        it(`should filter the html correctly for ${input}`, () => {
            expect(stripHtmlFilter(input)).toBe(expected);
        });
    });
});
