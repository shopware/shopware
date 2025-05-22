---
title: Content Schema for CMS
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Changed `Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer` to make the `contentSchema` field optional but still the `value` field is required.
___
# API
* Added a new field called `contentSchema`, which will contain the content schema for the already known `content` field. The `content` field is located within the slots. A child of the `content` field is the `value` field, which currently contains content with HTML markup.

This example HTML markup:
```html
"content": "<h2>Privacy <span style=\"color:rgb(217,140,140);\">777</span></h2><hr /><p style=\"text-align:right;\">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>",
```
will be transformed to:
```json
{
    "type": "doc",
    "content": [
        {
            "type": "heading",
            "attrs": {
                "level": 2
            },
            "content": [
                {
                    "type": "text",
                    "text": "Privacy "
                },
                {
                    "type": "text",
                    "marks": [
                        {
                            "type": "textStyle"
                        }
                    ],
                    "text": "777"
                }
            ]
        },
        {
            "type": "paragraph",
            "content": [
                {
                    "type": "text",
                    "text": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet."
                }
            ]
        }
    ]
}
```
