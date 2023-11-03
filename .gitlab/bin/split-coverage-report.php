#!/usr/bin/env php
<?php

const ALLOWED_CHUNKSIZE = 5 * 1024 * 1024; // 5 MB
const PACKAGES_XPATH = '//coverage/packages';
const PACKAGE_XPATH = '//coverage/packages/package';

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\DomCrawler\Crawler;

(new SingleCommandApplication())
    ->setName('split-coverage-report')
    ->addArgument('file', InputArgument::REQUIRED, 'Path to a cobertura.xml file')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $fileInfo = new SplFileInfo($input->getArgument('file'));

        if (!$fileInfo->isReadable()) {
            throw new RuntimeException(sprintf('File %s is not readable', $fileInfo->getPathname()));
        }

        $contents = file_get_contents($fileInfo->getPathname());

        if ($fileInfo->getSize() < ALLOWED_CHUNKSIZE) {
            $output->writeln(sprintf(
                'File %s is smaller than %d bytes, no need to split',
                $fileInfo->getPathname(),
                ALLOWED_CHUNKSIZE
            ));

            file_put_contents(
                sprintf("%s-%d.xml", $fileInfo->getBasename('.xml'), 0),
                $contents
            );

            return Command::SUCCESS;
        }

        $cobertura = new Crawler($contents);

        $packageCount = $cobertura->filterXPath(PACKAGE_XPATH)->count();
        $accumulatedPackageCount = 0;
        $chunksNeeded = (int) floor($fileInfo->getSize() / ALLOWED_CHUNKSIZE);
        $packageChunkSize = (int) floor($packageCount / $chunksNeeded);

        for ($i = 0; $i <= $chunksNeeded; $i++) {
            $childNodes = $cobertura
                ->filterXPath(PACKAGE_XPATH)
                ->slice($i * $packageChunkSize, $packageChunkSize);

            $c = addChildNodes(removeChildNodes(
                new Crawler($contents),
                PACKAGES_XPATH
            ), PACKAGES_XPATH, $childNodes);

            file_put_contents(
                sprintf("%s-%d.xml", $fileInfo->getBasename('.xml'), $i),
                $c->getNode(0)->ownerDocument->saveXML()
            );

            $accumulatedPackageCount += $childNodes->count();
        }

        if ($accumulatedPackageCount != $packageCount) {
            throw new RuntimeException(sprintf(
                'Accumulated package count (%d) does not match original package count (%d)',
                $accumulatedPackageCount,
                $packageCount
            ));
        }

        return Command::SUCCESS;
    })
    ->run();

function removeChildNodes(Crawler $crawler, string $parentSelector): Crawler
{
    $parentNode = $crawler->filterXPath($parentSelector)->getNode(0);
    $shallowClone = $parentNode->cloneNode(false);

    $grandParentNode = $parentNode->parentNode;

    $grandParentNode->removeChild($parentNode);
    $grandParentNode->appendChild($shallowClone);

    return $crawler;
}

/**
 * @param Crawler $crawler
 * @param string $parentSelector
 * @param iterable<DOMNode> $children
 * @return Crawler
 */
function addChildNodes(Crawler $crawler, string $parentSelector, iterable $children): Crawler
{
    $parentNode = $crawler->filterXPath($parentSelector)->getNode(0);
    $doc = $parentNode->ownerDocument;

    foreach ($children as $node) {
        $parentNode->appendChild($doc->importNode($node, true));
    }

    return $crawler;
}
