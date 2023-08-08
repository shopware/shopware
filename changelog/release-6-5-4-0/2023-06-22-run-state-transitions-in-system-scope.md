---
title: Run state transitions in system scope
issue: NEXT-28772
---
# Core
* Changed `\Shopware\Core\System\StateMachine\StateMachineRegistry::transition` to run all the transitions in system scope.
* Changed `\Shopware\Core\Content\Flow\Dispatching\FlowFactory::restore` to always restore flows in system scope.
