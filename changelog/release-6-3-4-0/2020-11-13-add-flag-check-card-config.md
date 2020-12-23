---
title: Add flag check card config level
issue: NEXT-11923
---
# Core
* Changed `src/Core/System/SystemConfig/Service/ConfigurationService::getConfiguration` to check feature flag in card config.
* Added `<xs:element name="flag" type="xs:string" minOccurs="0"/>` in `<xs:complexType name="card"></xs:complexType>`.
