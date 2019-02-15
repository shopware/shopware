module.exports = {
    "extends": "stylelint-config-sass-guidelines",
    "rules": {
        "indentation": 4,
        "max-nesting-depth": 2,
        "selector-no-qualifying-type": [
            true, {
                "ignore": ["attribute"]
            }
        ]
    }
};