import Criteria from 'src/core/data-new/criteria.data';

const types = [
    { type: 'avg', args: [] },
    { type: 'count', args: [] },
    { type: 'max', args: [] },
    { type: 'min', args: [] },
    { type: 'stats', args: [] },
    { type: 'sum', args: [] },
    { type: 'terms', args: [] },
    { type: 'filter', args: [] },
    { type: 'histogram', args: [] },
    { type: 'contains', args: [] },
    { type: 'equalsAny', args: ['field', []] },
    { type: 'range', args: [] },
    { type: 'equals', args: [] },
    { type: 'not', args: [] },
    { type: 'multi', args: [] }
];

describe('criteria.data.js', () => {
    it('returns the correct aggregation and filter types', () => {
        types.forEach(el => expect(Criteria[el.type](...el.args).type).toEqual(el.type));
    });
});
