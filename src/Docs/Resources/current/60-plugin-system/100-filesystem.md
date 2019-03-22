[titleEn]: <>(Filesystem)
[titleDe]: <>(Filesystem)
[wikiUrl]: <>(../plugin-system/filesystem?category=shopware-platform-en/plugin-system)

Shopware has a build-in [filesystem](http://flysystem.thephpleague.com/docs/), which is also usable in your plugin.
The filesystem has many build-in functionality to work with files.
Have a look at the documentation of [Flysystem](http://flysystem.thephpleague.com/docs/) by "The PHP League".

You don't need to configure anything. 
The `\Shopware\Core\Framework\Plugin::build()` method initializes a public and a private filesystem for your plugin by default.
You can access them via the DI container.
The IDs for the two services are generated on base of your plugin name.
Assuming your plugin is named `SwagExample`, the IDs will be `swag_example.filesystem.public` and `swag_example.filesystem.private`.

This example controller shows you how to use the filesystem
```xml
    ...
    <service id="SwagExample\Controller\TestController" public="true">
        <argument type="service" id="swag_example.filesystem.private"/>
        <argument type="service" id="swag_example.filesystem.public"/>
    </service>
    ...
```
As you can see, the controller has the two filesystem types as dependencies.
```php
<?php declare(strict_types=1);

namespace SwagExample\Controller;

use League\Flysystem\FilesystemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @var FilesystemInterface
     */
    private $privateFilesystem;

    /**
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    public function __construct(FilesystemInterface $privateFilesystem, FilesystemInterface $publicFilesystem)
    {
        $this->privateFilesystem = $privateFilesystem;
        $this->publicFilesystem = $publicFilesystem;
    }

    /**
     * @Route("/test-filesystem", name="test.filesystem", methods={"GET"})
     */
    public function testFilesystem(): Response
    {
        $this->privateFilesystem->write('test.txt', 'foo bar private');
        $this->publicFilesystem->write('test.txt', 'foo bar public');

        $privateTest = $this->privateFilesystem->read('test.txt');
        $publicTest = $this->publicFilesystem->read('test.txt');

        return new Response($privateTest. '<br>' . $publicTest);
    }
}
```
If you call the route `http://www.your-domain.com/test-filesystem`, the output would looks like this:
```text
foo bar private
foo bar public
```
The example writes two files. One to the private space and one to the public space.
The private file is located under `files/plugins/swag_example/text.txt` and is only accessible via the filesystem.
The public file is located under `public/plugins/swag_example/test.txt` and can, additional to the filesystem, be accessed via URL: `http://www.your-domain.com/plugins/swag_example/test.txt`.
