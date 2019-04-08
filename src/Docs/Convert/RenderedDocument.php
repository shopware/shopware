<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class RenderedDocument
{
    /**
     * @var string
     */
    private $html;
    /**
     * @var array
     */
    private $images;

    public function __construct(string $html, array $images)
    {
        $this->html = $html;
        $this->images = $images;
    }

    public function addImage(string $key, string $path)
    {
        $this->images[$key] = $path;
    }

    public function getContents(array $imageMap = []): string
    {
        $result = $this->html;

        foreach ($imageMap as $key => $link) {
            $result = str_replace($key, $link, $result);
        }

        return $result;
    }

    public function getImages(): array
    {
        return $this->images;
    }
}
