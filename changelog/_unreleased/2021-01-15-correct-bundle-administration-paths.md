---
title:              Correct resource path for administration javascript files of bundles 
issue:              NEXT-13733
author:             Niklas Büchner
author_email:       niklas.buechner@pickware.de
---
# Administration
* Changed the resource path determination for administration javascript files of bundles in`src/Core/Framework/Api/Controller/InfoController.php`
* Added testcase for '*bundle' bundles to `src/Core/Framework/Test/Api/Controller/InfoControllerTest.php` and 
  fixture `src/Core/Framework/Test/Api/Controller/fixtures/InfoController/Resources/public/administration/js/some-functionality-bundle.js`  
