module.exports = {
    "extends": "stylelint-config-sass-guidelines",
    "rules": {
        "indentation": 4,
        "max-nesting-depth": 3,
        "order/properties-alphabetical-order": null,
        "scss/at-extend-no-missing-placeholder": null,
        "selector-no-qualifying-type": [
            true, {
                "ignore": ["attribute", "class"]
            }
        ],
        "selector-class-pattern": null
    }
};