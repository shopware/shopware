module.exports = {
    extends: 'stylelint-config-sass-guidelines',
    rules: {
        indentation: 4,
        'max-nesting-depth': 3,
        'order/properties-alphabetical-order': null,
        'scss/at-extend-no-missing-placeholder': null,
        'selector-class-pattern': [
            '^[a-z0-9\\-]+$',
            {
                message:
                    'Selector should be written in lowercase with hyphens (selector-class-pattern)'
            }
        ],
        'selector-no-qualifying-type': [
            true, {
                ignore: ['attribute', 'class']
            }
        ]
    }
};
