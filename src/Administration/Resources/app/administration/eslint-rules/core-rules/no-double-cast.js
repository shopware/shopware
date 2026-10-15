/**
 * @sw-package framework
 */

const createSelectorRule = require('./create-selector-rule');

module.exports = createSelectorRule({
    description: 'Disallow `as unknown as` casts',
    selector: "TSAsExpression[expression.type='TSAsExpression'][expression.typeAnnotation.type='TSUnknownKeyword']",
    message: 'Avoid `as unknown as`. Fix the type, or narrow it with a type guard.',
});
