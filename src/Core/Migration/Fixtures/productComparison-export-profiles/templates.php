<?php declare(strict_types=1);

return [
    'idealo_old' => file_get_contents(__DIR__ . '/old-template-idealo.csv.twig'),
    'idealo_new' => file_get_contents(__DIR__ . '/new-template-idealo.csv.twig'),
    'billiger_old' => file_get_contents(__DIR__ . '/old-template-billiger.csv.twig'),
    'billiger_new' => file_get_contents(__DIR__ . '/new-template-billiger.csv.twig'),
    'google_old' => file_get_contents(__DIR__ . '/old-template-google.xml.twig'),
    'google_new' => file_get_contents(__DIR__ . '/new-template-google.xml.twig'),
];
