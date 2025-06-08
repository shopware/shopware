---
title: Fix bug can't create a commercial customer with shipping address
issue: NEXT-11584
---
# Storefront
* Changed `{% block component_address_personal_account_type %}{% endblock %}` in `src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig` to be able to create a commercial customer with shipping address.
* Changed `{% block component_account_register_address_billing_fields %}{% endblock %}` in `src/Storefront/Resources/views/storefront/component/account/register.html.twig` to handle different addresses when validation failed.
