/**
 * @package admin
 */

import genericRuleCondition from 'src/core/service/utils/generic-rule-condition.utils';

describe('src/core/service/utils/generic-rule-condition.utils.js', () => {
    it.each([
        {
            fieldType: 'datetime',
            expected: 'sw-datepicker.datetime.placeholder',
        },
        { fieldType: 'date', expected: 'sw-datepicker.date.placeholder' },
        { fieldType: 'time', expected: 'sw-datepicker.time.placeholder' },
        { fieldType: 'foo', expected: '' },
    ])('should return "$expected" snippet when the field type is $fieldType', async ({ fieldType, expected }) => {
        expect(genericRuleCondition.getPlaceholderSnippet(fieldType)).toBe(expected);
    });
});
