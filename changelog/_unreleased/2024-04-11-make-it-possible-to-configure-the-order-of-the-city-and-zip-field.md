---
title: Make it possible to configure the order of the city and ZIP field
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Added setting component for address settings to configure the order of the ZIP and city field in the address form
* Changed address form component to consider the setting `core.address.showZipcodeInFrontOfCity`
* Added blocks `component_address_form_zipcode_field`, `component_address_form_city_field` and `component_address_form_zipcode_city_fields`
