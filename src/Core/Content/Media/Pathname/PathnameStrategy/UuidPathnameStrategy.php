<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;

class UuidPathnameStrategy implements PathnameStrategyInterface
{
    /**
     * @var PlainPathnameStrategy
     */
    private $plainPathnameStrategy;

    public function __construct(PlainPathnameStrategy $plainPathnameStrategy)
    {
        $this->plainPathnameStrategy = $plainPathnameStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'uuid';
    }

    /**
     * {@inheritdoc}
     */
    public function encode(string $filename, string $id): string
    {
        if (empty($filename)) {
            throw new EmptyMediaFilenameException();
        }

        if (empty($id)) {
            throw new EmptyMediaIdException();
        }

        return mb_substr($id, 0, 16)
            . '/'
            . mb_substr($id, 16)
            . '/'
            . $this->plainPathnameStrategy->encode($filename, $id);
    }
}
