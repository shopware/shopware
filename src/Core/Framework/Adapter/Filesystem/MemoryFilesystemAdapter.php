<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFile;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @see https://github.com/thephpleague/flysystem/issues/1477
 */
#[Package('framework')]
class MemoryFilesystemAdapter extends InMemoryFilesystemAdapter
{

}
