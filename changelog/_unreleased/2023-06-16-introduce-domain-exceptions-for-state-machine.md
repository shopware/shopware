---
title: Introduce domain exception for state machine
issue: NEXT-28609
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Deprecated the following exceptions in replacement for Domain Exceptions
    * `Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException`
    * `Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException`
    * `Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException`
    * `Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException`
    * `Shopware\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException`
