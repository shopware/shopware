<?php declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

$filesystem = new Filesystem();

return [
    'idealo_old' => $filesystem->readFile(__DIR__ . '/old-template-idealo.csv.twig'),
    'idealo_new' => $filesystem->readFile(__DIR__ . '/new-template-idealo.csv.twig'),
    'billiger_old' => $filesystem->readFile(__DIR__ . '/old-template-billiger.csv.twig'),
    'billiger_new' => $filesystem->readFile(__DIR__ . '/new-template-billiger.csv.twig'),
    'google_old' => $filesystem->readFile(__DIR__ . '/old-template-google.xml.twig'),
    'google_new' => $filesystem->readFile(__DIR__ . '/new-template-google.xml.twig'),
];
