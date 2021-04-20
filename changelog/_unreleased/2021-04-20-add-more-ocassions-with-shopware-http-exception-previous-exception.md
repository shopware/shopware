---
title: Add and fill "previous exception" parameter to multiple exception types
author: Joshua Behrens
author_email: behrens@heptacom.de
author_github: @JoshuaBehrens
---
# Core
* Added an optional `\Throwable $previous` parameter to constructors of child classes of the `ShopwareHttpException` when missing
* Changed calls to Exception constructors that have an additional parameter for the previous exception
