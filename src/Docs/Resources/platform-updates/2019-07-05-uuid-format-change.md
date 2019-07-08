[titleEn]: <>(UUID format change)

Shopware 6 no longer accepts upper case letters or dashes in UUIDs. So the format is now a single string containing numbers `0` til `9` and the characters `a` til `f`.    

The reason is that Shopware would formerly accept the dashed version and upper case letters (`74d25156-60e6-444c-A177-A96E67ECfC5F`) but strip the information without being able to reproduce them for the response format factually deleting information.

**Valid**: 74d2515660e6444ca177a96e67ecfc5f
**Invalid**: 74D2515660E6444CA177A96E67ECFC5F
**Invalid**: 74d25156-60e6-444c-a177-a96e67ecfc5f
