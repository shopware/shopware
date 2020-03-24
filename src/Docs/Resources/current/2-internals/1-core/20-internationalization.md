[titleEn]: <>(Internationalization)
[hash]: <>(article:internationalization)

Shopware 6 supports internationalization as a core concept. The translation System supports three levels. 

1. A *system language* (fixed en-GB) - the fallback that requires a translation. 
2. A *root language*  - The actual main content language.
3. A *derived language*  - A derived language to overwrite certain terms.

For example a typical german setup would look like this:

```
<en-GB>
└── de-DE
    └── de-CH
    └── de-AT
```
Or a typical english setup in contrast:

```
<en-GB>
└── en-US
    └── en-AU
```
### Example

A product's name is "Schneebesen" but in a sales channel for Switzerland,
you'll get "Schwingbesen" as that would be the correct translation. All other,
not especially translated field, will stick to the German language `de-DE`.

## Support

Translations in Shopware 6 are supported by two subsystems all correlating to the same basic rules:

Snippets
 : Snippets are dynamic translation texts that can be managed through the Admin API
 
[Data Abstraction Layer](./20-data-abstraction-layer/__categoryInfo.md)
 : All customer facing data is by default translatable follow the link to see how.
 
 

