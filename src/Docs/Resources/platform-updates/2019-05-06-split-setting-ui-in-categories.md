[titleEn]: <>(Split setting UI in categories)

The Settings are now split up in the categories `Shop`, `System` and `Plugins`.

To add your setting to the different categories you just have to extend the right template block.

Instead of the old `sw_settings_content_card_slot_default` block you now have three specific blocks for each category:

`sw_settings_content_card_slot_shop`

`sw_settings_content_card_slot_system`

`sw_settings_content_card_slot_plugins`