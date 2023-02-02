[titleEn]: <>(Plugin changelog)
[hash]: <>(article:plugin_changelog)

## Overview
In this guide, you'll learn how to maintain local changelogs for your plugin.
`Local changelogs` mean, that these changelogs are shipped with your plugin and serve as a fallback,
if for whatever reason the changelogs from the `Shopware Account` are not present.
Creating and maintaining a local changelog for your plugin is pretty straight forward.
All you need to do is to create a `CHANGELOG.md` in the root of your plugin and stick to our markdown specifications.

```markdown
# 1.0.0
- first item
- second item

# 1.1
* first item
* second item
```
*Valid CHANGELOG.md*

| Markdown | Meaning                |
|----------|------------------------|
| #        | Defines a Version      |
| - or *   | Define Items (changes) |

## Translation
Your goal should be to keep these files simple and clean. Translations are split up in separate files.
The file `CHANGELOG.md` is for the locale `en-GB`.
If you want to maintain another locale the format looks like this: `CHANGELOG_??-??.md`.
Whereas `??-??` represents the locale you want to create.
For example, a changelog for German would be `CHANGELOG_de-DE.md`.
