[titleEn]: <>(Removed Defaults::LANGUAGE_SYSTEM_DE)

We've removed the constant `Defaults::LANGUAGE_SYSTEM_DE`.
Furthermore it's not guaranteed that `Defaults::LANGUAGE_SYSTEM` references the 'en-GB' language. (The same holds for `Defaults::CURRENCY`). The installer and migration assistant may change the underlying language/currency.

### Example

If you need the id of the `de-DE` language, you have to search it by using the translationCode:
```php

$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('language.translationCode.code', 'de-DE'));
$language = $languageRepository->search($criteria, $context)->first();
dump($language->getId());
```
