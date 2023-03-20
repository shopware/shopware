---
title:              Make requeuing dead messages respect recoverable exceptions
issue:              NEXT-25825
author:             Dominik Pretzsch
author_email:       dominik.pretzsch@3m5.de
author_github:      @blacksheep--
---
# Core
*  Added check to make sure dead messages implementing RecoverableExceptionInterface won't be canceled after MAX_RETRIES failures in `src/Framework/MessageQueue/DeadMessage/RequeueDeadMessagesService.php`
