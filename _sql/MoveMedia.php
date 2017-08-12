#!/usr/bin/env php
<?php

use Shopware\Media\Strategy\Md5Strategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

require __DIR__ . '/../vendor/autoload.php';

$path = __DIR__ . '/../web/media';
$md5 = new Md5Strategy();
$finder = new Finder();
$files = $finder->in($path)->files()->getIterator();

$fs = new Filesystem();
foreach ($files as $file) {
    $fs->mkdir(dirname($path . '/' . $md5->encode($file->getFilename())));
    $fs->rename($file->getRealPath(), $path . '/' . $md5->encode($file->getFilename()), true);
}