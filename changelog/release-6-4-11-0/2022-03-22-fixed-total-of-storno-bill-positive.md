---
title: Fixed total of storno bill positive
issue: NEXT-17463
---
# Core
* Changed `Core/Checkout/Document/DocumentGenerator/StornoGenerator.php` to update rawTotal & totalPrice into negative when generating storno document
