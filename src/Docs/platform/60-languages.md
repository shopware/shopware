# Languages

This section describes the language system of the shopware platform.
Languages are defined in the `Shopware\Api\Language\Definition\LanguageDefinition` entity.
Each language requires a name and a locale id on which it is based on.
The locale id is used for libraries to identify a internationalized localisation.

*This may cause the question: Why can i define own languages if i have to define a locale.*

Defining own language has different benefits:
* I can create customized languages for different customer groups: `Yoda dialect`
* I can define languages with own dialects
* **I can inherited languages**

Yes, the shopware platform allows to define language inheritance. Like the `data inhertiance` it allows to define a fallback which are used if the request language data not exists.
This prevents duplicate data for same languages. 
Adopted we have a language with a specify dialect where only ten words in the language are different, without the language inheritance it would be necessary to clone the whole language data and modify only the ten words.
With the language inheritance, you would create a new language for the dialect and add a parent language.
Now you can modify the ten words in your data translations for the specify language and all other translations are loaded from the parent.
This has the benefit, in case the parent translations are modified, the inherited language are synchronized automatically.
A parent language can be defined over the `parentId` property.

Create a language over `POST api/language`
```json
{
  "id": "xxxxxxxxxxxxxxxxxxx",
  "name": "English" 
}
```

Create a child language over `POST api/language`
```json
{
  "parentId": "xxxxxxxxxxxxxxxxxxx",
  "name": "Yoda dialect" 
}
```

