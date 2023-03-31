---
title: Default handling for non specified salutations
date: 2023-06-28
area: core
tags: [adr, salutation]
---

## Context
The current implementation of the salutation in Shopware 6 needs to handle cases where the salutation is not specified by the customer or administrator. To address this requirement and promote inclusivity, we have updated the default salutation to "not_specified" for unspecified salutations in our Shopware 6 platform.

## Decision
We have modified the existing salutation handling in Shopware 6 to update the default value to "not_specified" when the salutation is null. This decision was made based on the following considerations:

* Inclusivity: By offering a default salutation of "not_specified" for null values, we promote inclusivity and ensure that all customers are appropriately addressed, even when salutation data is missing.
* Customer Experience: Providing a default salutation ensures consistency in customer communications and prevents any confusion or misinterpretation when a salutation is not explicitly specified.
* Non-Deletable Default Salutation: It has been decided that the "not_specified" salutation, being the default value for unspecified salutations, should not be deletable by the shop owner. This ensures that there is always a fallback option available, guaranteeing a consistent experience for customers.

## Consequences
As a result of this decision, the following consequences will occur:

* Improved Default Handling: When a customer or administrator does not specify a salutation, the default value will be automatically set to "not_specified." This default value itself is configurable by the shop owner. They have the flexibility to customize the "not_specified" value to their preferred salutation or leave it as it is to use the generic "not_specified" salutation.
* Enhanced Inclusivity: Customers who have not specified their salutation will be addressed using the default "not_specified" salutation, reflecting our commitment to inclusivity and respect within our platform.
* Code Changes: The necessary code changes have been implemented to update the default handling of null salutations. This includes validation checks, database updates, and modifications to relevant logic to accommodate the "not_specified" default value.
* Different Default Values in Specific Locations: The default values used in specific locations within the platform are as follows:
  * Letters and Documents: When generating letters or documents where a salutation is required, the default value will be "Dear Customer" or an appropriate alternative if customization is allowed. This ensures a professional and personalized approach in written communications.
  * Email Communications: In email communications, the default value will be "Hello" or an alternative greeting if customization is allowed. This provides a friendly and welcoming tone in electronic correspondences.
  * User Interfaces: Within the user interfaces of the Shopware 6 platform, the default value will be displayed as "not_specified" for customers who have not specified a salutation. This allows for a neutral and inclusive representation in the platform's user-facing components.
* Testing and Quality Assurance: Rigorous testing procedures will be conducted to ensure the accuracy and reliability of the updated default handling. Quality assurance measures will be in place to identify and address any potential issues.
