[titleEn]: <>(Black-/Whitelisting)
[hash]: <>(article:dal_black_white_listing)

Black-/Whitelisting is an extension to define the visibility of Entities for certain Rules.

## Blacklisting

Blacklisting defines that the Entity is NOT visible/readable for a Consumer who matches the Criteria of the Blacklist Rule


## Whitelisting

Whitlisting defines that the Entity is ONLY visible/readable for a Consumer who matches the Criteria of the Whitelist Rule

## Add Black-/Whitelist Support for an Entity

To add Black-/Whitelist Support to an Entity you add the corresponding Fields to the EntityDefinition 

/Core/Framework/DataAbstractionLayer/Field/BlacklistRuleField.php
/Core/Framework/DataAbstractionLayer/Field/WhitelistRuleField.php
```php
(new BlacklistRuleField()),
(new WhitelistRuleField()),
```

### Read/Search with Black-/Whitelist Support

To Support the Black-/Whitelisting for Searches and Reads the Rules for the currently applied Black-/Whitelisting have to be in the Context (_/Core/Framework/Context.php_)   
