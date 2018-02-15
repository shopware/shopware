import JsonApiParser from 'src/core/service/jsonapi-parser.service';

describe('core/service/jsonapi-parser.service.js', () => {
    it('should reject when we are providing an array, number, undefined or null', () => {
        const arrayParser = JsonApiParser([1, 2, 3]);
        expect(arrayParser).is.equal(null);

        const nestedArrayParser = JsonApiParser([
            { id: 42, name: 'foo' },
            { id: 92, name: 'bar' }
        ]);
        expect(nestedArrayParser).is.equal(null);

        const numberParser = JsonApiParser(42);
        expect(numberParser).is.equal(null);

        const negativeNumberParser = JsonApiParser(-3);
        expect(negativeNumberParser).is.equal(null);

        const undefinedParser = JsonApiParser(undefined);
        expect(undefinedParser).is.equal(null);

        const nullParser = JsonApiParser(null);
        expect(nullParser).is.equal(null);
    });

    it('should not parse a malformed JSON string', () => {
        const brokenJsonParser = JsonApiParser('{foo:"bar"}');
        expect(brokenJsonParser).is.equal(null);
    });

    it('should parse a valid JSON string which is not following the spec', () => {
        const validJsonParser = JsonApiParser('{"foo":"bar"}');
        expect(validJsonParser).is.deep.equal({
            foo: 'bar'
        });
    });

    it('should parse a valid JSON string which follows the spec', () => {
        const validJsonApiParser = JsonApiParser(JSON.stringify({
            data: [{
                id: 1,
                type: 'article',
                attributes: {
                    title: 'Foo bar'
                },
                relationships: {
                    author: {
                        data: { id: 1, type: 'people' }
                    }
                }
            }],
            included: [{
                type: 'people',
                id: 1,
                attributes: {
                    name: 'Peter'
                }
            }]
        }));

        expect(JSON.stringify(validJsonApiParser)).is.equal(JSON.stringify({
            data: [{
                id: 1,
                type: 'article',
                links: {},
                meta: {},
                title: 'Foo bar',
                author: [{
                    id: 1,
                    type: 'people',
                    links: {},
                    meta: {},
                    name: 'Peter'
                }]
            }],
            parsed: true
        }));
    });
});
