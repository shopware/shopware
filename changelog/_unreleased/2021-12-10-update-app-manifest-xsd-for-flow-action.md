---
title: Update app manifest xsd for flow action.
issue: NEXT-19083
flag: FEATURE_NEXT_17540
---
# Core
* Added following classes to parser data from flow-action.xml:
  * `src/Core/Framework/App/FlowAction/FlowAction.php`
  * `src/Core/Framework/App/FlowAction/Xml/Action.php`
  * `src/Core/Framework/App/FlowAction/Xml/Actions.php`
  * `src/Core/Framework/App/FlowAction/Xml/Component.php`
  * `src/Core/Framework/App/FlowAction/Xml/Config.php`
  * `src/Core/Framework/App/FlowAction/Xml/Headers.php`
  * `src/Core/Framework/App/FlowAction/Xml/InputField.php`
  * `src/Core/Framework/App/FlowAction/Xml/Metadata.php`
  * `src/Core/Framework/App/FlowAction/Xml/Parameter.php`
  * `src/Core/Framework/App/FlowAction/Xml/Parameters.php`
* Added new xml schema definition `src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd`
