[titleEn]: <>(Consistent locale codes)

Until now we used two locale code standards.

Bcp-47 inside vue.js (administration) and IEC_15897 inside the Php backend.

Now we use the BCP-47 standard for both. This means, that the locale codes changed from `en_GB` to `en-GB`.

Where does this effect you?

* First you need to reinitialize your Shopware installation after you pulled these changes (./psh.phar init)
* composer.json of your plugins
* Changelogs of your plugins
* Locale Repository
* Snippet files of all modules


### composer.json

You have to change the locale codes inside the extra section of your plugin composer.json from:

```json
"extra": {
  "shopware-plugin-class": "Swag\\Example",
  "copyright": "(c) by shopware AG",
  "label": {
    "de_DE": "Example Produkte für Shopware",
    "en_GB": "Example Products for Shopware"
  }
},
```

to:

```json

"extra": {
  "shopware-plugin-class": "Swag\\Example",
  "copyright": "(c) by shopware AG",
  "label": {
    "de-DE": "Example Produkte für Shopware",
    "en-GB": "Example Products for Shopware"
  }
},
```

### Changelogs

The `en-GB` changelog file still is: `CHANGELOG.md`.

The format for all other locales changed from `CHANGELOG-??_??.md` to `CHANGELOG_??-??.md`. For example a german changelog file changed from `CHANGELOG-de_DE.md` to `CHANGELOG_de-DE.md`.

### Locale Repository

If you use the locale repository inside your code, the locale codes will now return in the new format.

### Snippet files of all modules

We renamed all snippet files, from `en_GB.json` to `en-GB.json`.

For consistency, you should do the same in your plugins.
