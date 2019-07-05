[titleEn]: <>(UUID format change)

Dashed UUIDs are no longer allowed or supported by Shopware 6. So the format is now a single string containing numbers `0` til `9` and the characters `a` til `f` and `A` til `F`.

The reason is that Shopware would formerly accept the dashed version (`74d25156-60e6-444c-a177-a96e67ecfc5f`) and strip the dashes without being able to reproduce them for the response format factually deleting information.

**Valid**: 74d2515660e6444ca177a96e67ecfc5f
**Invalid**: 74d25156-60e6-444c-a177-a96e67ecfc5f
