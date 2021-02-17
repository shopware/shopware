---
title: Remove deprecations along with removal of the feature flag of rule assignment tab
issue: NEXT-12461
author: Ramona Schwering
author_email: r.schwering@shopware.com 
author_github: leichteckig
---
# Administration
* Removed `sw_settings_rule_detail_content_card` block due to being deprecated
    * Block was renamed to `sw_settings_rule_detail_base_content_card` and moved to `sw-settings-rule-detail-base.html.twig`
* Removed all child blocks of `sw_settings_rule_detail_content_card` alongside that:
    * `sw_settings_rule_detail_content_card_field_name`
    * `sw_settings_rule_detail_content_card_field_priority`
    * `sw_settings_rule_detail_content_card_field_type`
* Removed `sw_settings_rule_detail_conditions_card` block due to being deprecated
    * Block was renamed to `sw_settings_rule_detail_base_conditions_card` and moved to `sw-settings-rule-detail-base.html.twig`
