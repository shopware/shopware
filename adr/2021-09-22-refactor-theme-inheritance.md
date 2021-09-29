# 2021-09-22 - Refactor theme inheritance

## Context
Currently the themes can only inherit config fields from the default Storefront theme.
Also this inheritence is only a snapshot by activation time of the theme - The configs are copied to the new theme and changes to the default theme config will not appear in the new theme without a re-activation.
The different possibilities to inherit different parts of a theme, like scripts, templates and config, can also cause problems on later updates.

To take this points into account we have two possible changes.
1. Add a new inheritance key for the `configFields` in the `theme.json` which allow a theme to inherit its config from other themes in a given order:
```json
"configInheritance": [
        "@Storefront",
        "@PreviousTheme",
        "@MyDevelopmentTheme"
    ],
```
<details>
  <summary>Complete theme.json with part inheritances</summary>

```json
{
    "name": "MyDevelopmentTheme",
    "author": "Shopware AG",
    "views": [
        "@Storefront",
        "@Plugins",
        "@MyDevelopmentTheme"
    ],
    "style": [
        "app/storefront/src/scss/overrides.scss",
        "@Storefront",
        "app/storefront/src/scss/base.scss"
    ],
    "script": [
        "@Storefront",
        "app/storefront/dist/storefront/js/my-development-theme.js"
    ],
    "asset": [
        "@Storefront",
        "app/storefront/src/assets"
    ],
    "configInheritance": [
        "@Storefront",
        "@PreviousTheme",
        "@MyDevelopmentTheme"
    ],
    "config": {
        "blocks": {
            "exampleBlock": {
                "label": {
                    "en-GB": "Example block",
                    "de-DE": "Beispiel Block"
                }
            }
        },
        "sections": {
            "exampleSection": {
                "label": {
                    "en-GB": "Example section",
                    "de-DE": "Beispiel Sektion"
                }
            }
        },
        "fields": {
            "my-single-test-select-field": {
                "editable": false
            },
            "my-single-select-field": {
                "label": {
                    "en-GB": "Select a font size",
                    "de-DE": "Wähle ein Schriftgröße"
                },
                "type": "text",
                "value": "24",
                "custom": {
                    "componentName": "sw-single-select",
                    "options": [
                        {
                            "value": "16",
                            "label": {
                                "en-GB": "16px",
                                "de-DE": "16px"
                            }
                        },
                        {
                            "value": "20",
                            "label": {
                                "en-GB": "20px",
                                "de-DE": "20px"
                            }
                        },
                        {
                            "value": "24",
                            "label": {
                                "en-GB": "24px",
                                "de-DE": "24px"
                            }
                        }
                    ]
                },
                "editable": true,
                "block": "exampleBlock",
                "section": "exampleSection"
            },
            "usps-positions": {
                "label":
                {
                    "en-GB": "Position",
                    "de-DE": "Position"
                },
                "scss": true,
                "type": "text",
                "value": [
                    "top",
                    "bottom"
                ],
                "custom": {
                    "componentName": "sw-multi-select",
                    "options": [
                        {
                            "value": "bottom",
                            "label":
                            {
                                "en-GB": "bottom",
                                "de-DE": "unten"
                            }
                        },
                        {
                            "value": "top",
                            "label":
                            {
                                "en-GB": "top",
                                "de-DE": "oben"
                            }
                        },
                        {
                            "value": "middle",
                            "label":
                            {
                                "en-GB": "middle",
                                "de-DE": "mittel"
                            }
                        }
                    ]
                },
                "editable": true,
                "tab": "usps",
                "block": "exampleBlock",
                "section": "exampleSection"
            }
        }
    }
}
```
</details>

2. Add a new inheritance key for the whole inheritance in the `theme.json` which will cause the whole theme to be a descendant of the configured themes.
```json
"inheritance": [
        "@Storefront",
        "@PreviousTheme",
        "@MyDevelopmentTheme"
    ],
```
<details>
  <summary>Complete theme.json with whole inheritance</summary>

```json
{
    "name": "MyDevelopmentTheme",
    "author": "Shopware AG",
    "inheritance": [
        "@Storefront",
        "@PreviousTheme",
        "@MyDevelopmentTheme"
    ],
    "config": {
        "blocks": {
            "exampleBlock": {
                "label": {
                    "en-GB": "Example block",
                    "de-DE": "Beispiel Block"
                }
            }
        },
        "sections": {
            "exampleSection": {
                "label": {
                    "en-GB": "Example section",
                    "de-DE": "Beispiel Sektion"
                }
            }
        },
        "fields": {
            "my-single-test-select-field": {
                "editable": false
            },
            "my-single-select-field": {
                "label": {
                    "en-GB": "Select a font size",
                    "de-DE": "Wähle ein Schriftgröße"
                },
                "type": "text",
                "value": "24",
                "custom": {
                    "componentName": "sw-single-select",
                    "options": [
                        {
                            "value": "16",
                            "label": {
                                "en-GB": "16px",
                                "de-DE": "16px"
                            }
                        },
                        {
                            "value": "20",
                            "label": {
                                "en-GB": "20px",
                                "de-DE": "20px"
                            }
                        },
                        {
                            "value": "24",
                            "label": {
                                "en-GB": "24px",
                                "de-DE": "24px"
                            }
                        }
                    ]
                },
                "editable": true,
                "block": "exampleBlock",
                "section": "exampleSection"
            },
            "usps-positions": {
                "label":
                {
                    "en-GB": "Position",
                    "de-DE": "Position"
                },
                "scss": true,
                "type": "text",
                "value": [
                    "top",
                    "bottom"
                ],
                "custom": {
                    "componentName": "sw-multi-select",
                    "options": [
                        {
                            "value": "bottom",
                            "label":
                            {
                                "en-GB": "bottom",
                                "de-DE": "unten"
                            }
                        },
                        {
                            "value": "top",
                            "label":
                            {
                                "en-GB": "top",
                                "de-DE": "oben"
                            }
                        },
                        {
                            "value": "middle",
                            "label":
                            {
                                "en-GB": "middle",
                                "de-DE": "mittel"
                            }
                        }
                    ]
                },
                "editable": true,
                "tab": "usps",
                "block": "exampleBlock",
                "section": "exampleSection"
            }
        }
    }
}
```
</details>

## Decision


## Consequences
The Consequences for the two approaches are described below:
### 1. New config inheritance:
* The inheritance **can still cause incompatibility errors** because of missing subsets of a dependend theme.
* The current themes will work as always but one can also add an inheritance for the config fields.
### 2. New config for whole inheritance:
* The inheritance **can no longer cause incompatibility errors** because of missing subsets of a dependend theme.
* The current themes will stop to work if they use the `views`, `style`, `script` or `asset` keys in their config. This can be made backwards compatible for a whole major phase but no longer
* The inheritance order cannot differ between e.g. templates and scripts and config. - This is wanted!
### Both approaches:
* The inhertiance will no longer be a snapshot, but a dynamic copy of the inherited themes (The changes of child themes will be accounted by the new theme automaticaly)
* The admin for the themes will get an inheritation mechanism which allows users to decide if a field will use its inherited or a new value (simmiliar to productvariant inherited fields)
* Themes which are dependend on other themes than the default storefront theme, need to add the other themes into there composer.json as `required` to prevent incomplete setups.
```json
 "require": {
        "swag/previous-theme": "~1.1"
    },
```

<details>
  <summary>Example complete composer.json</summary>

```json
{
    "name": "swag/my-development-theme",
    "description": "My Development Theme",
    "type": "shopware-platform-plugin",
    "version": "1.7",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "MyDevelopmentTheme\\": "src/"
        }
    },
    "require": {
        "swag/previous-theme": "~1.1"
    },
    "extra": {
        "shopware-plugin-class": "MyDevelopmentTheme\\MyDevelopmentTheme",
        "label": {
            "de-DE": "Theme MyDevelopmentTheme plugin",
            "en-GB": "Theme MyDevelopmentTheme plugin"
        }
    }
}
```
</details>

## Notes and Questions
* In shopware 5  different themes exist for **bare** (_only twig_) and **responsive** (_twig, css, script_) to allow plugin developers to only use the twig templates and no js or css
* Are there current examples where the partly inheritance is really needed?
* 
