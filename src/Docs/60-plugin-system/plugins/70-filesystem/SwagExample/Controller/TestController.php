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

        return new Response($privateTest . '<br>' . $publicTest);
    }
}
