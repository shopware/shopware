module.exports = {
    extends: 'stylelint-config-recommended-scss',
    plugins: ['stylelint-prettier'],
    rules: {
        'prettier/prettier': [true, {
            'printWidth': 100,
            'tabWidth': 4,
            'singleQuote': true,
        }],
        'max-nesting-depth': [3, {
            'ignore': ['blockless-at-rules', 'pseudo-classes'],
            'severity': 'warning',
        }],
        'no-descending-specificity': null,
        'scss/at-extend-no-missing-placeholder': null,
        'scss/no-global-function-names': null,
        'at-rule-disallowed-list': 'always',
        'selector-class-pattern': [
            '^[a-z0-9\\-]+$',
            {
                message:
                    'Selector should be written in lowercase with hyphens (selector-class-pattern)',
            },
        ],
        'selector-no-qualifying-type': [
            true, {
                ignore: ['attribute', 'class'],
            },
        ],
    },
};
