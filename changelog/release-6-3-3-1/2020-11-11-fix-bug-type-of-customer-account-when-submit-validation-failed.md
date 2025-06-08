---
title: Fix bug type of customer account automatically move from Commercial to Private when submit validation failed on Register Page.
issue: NEXT-11817
---
# Storefront
* Changed `{% block component_address_personal_account_type_select %}{% endblock %}` in `src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig` to handle the value of the account type of customer.
