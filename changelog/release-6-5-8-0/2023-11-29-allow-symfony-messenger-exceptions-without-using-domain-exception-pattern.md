---
title: Allow Symfony messenger exceptions without using domain exception pattern
issue: NEXT-32009
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added the following exceptions to the list of allowed exceptions in `Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\DomainExceptionRule`:
  * `Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException`
  * `Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException`
