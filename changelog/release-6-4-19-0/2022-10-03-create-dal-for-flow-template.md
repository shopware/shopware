---
title: Create DAL for flow template
issue: NEXT-23154
---
# Core
* Added new table `flow_template` to stored flow template data.
* Added entities, definition and collection for table `flow_template` at `Shopware\Core\Content\Flow\Aggregate\FlowTemplate`.
* Added migration `Migration1659257296GenerateFlowTemplateDataFromEventAction` to generate default flow template data.
* Added `Shopware\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField` to store config data.
* Added `Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer`.
