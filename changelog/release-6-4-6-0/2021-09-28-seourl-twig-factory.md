---
title: Factory to build Seo Url Twig Environment
issue: NEXT-17849
author: Björn Herzke                          
author_email: bjoern.herzke@brandung.de                   
author_github: @wrongspot
---
# Core
* Added new class `src/Core/Content/Seo/SeoUrlTwigFactory.php`
___
# Upgrade Information

### Create own SeoUrl Twig Extension
Create a regular Twig extension, instead of tagging it with name `twig.extension` use tag name `shopware.seo_url.twig.extension`

Example Class:
```php
<?php declare(strict_types=1);

namespace SwagExample\Core\Content\Seo\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ExampleTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('lastBigLetter', [$this, 'convert']),
        ];
    }

    public function convert(string $text): string
    {
        return strrev(ucfirst(strrev($text)));
    }
}
```

Example service.xml:
```xml
<service id="SwagExample\Core\Content\Seo\Twig\ExampleTwigFilter">
    <tag name="shopware.seo_url.twig.extension"/>
</service>
```
