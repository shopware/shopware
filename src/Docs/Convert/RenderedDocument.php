<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class RenderedDocument
{
    private const GLOBAL_STYLE_CONTENT = <<<EOD
<style type="text/css">

dl dt {
    font-weight: bolder;
    margin-top: 1rem;
}

dl dd {
    padding-left: 2rem;
}

h2 code {
    font-size: 32px;
}

.category--description ul {
    padding-left: 2rem;
}

dt code,
li code,
table code,
p code {
    font-family: monospace, monospace;
    background-color: #f9f9f9;
    font-size: 16px;
}
</style>

EOD;

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

    public function addImage(string $key, string $path): void
    {
        $this->images[$key] = $path;
    }

    public function getContents(array $imageMap = []): string
    {
        $result = $this->html;

        foreach ($imageMap as $key => $link) {
            $result = str_replace($key, $link, $result);
        }

        return self::GLOBAL_STYLE_CONTENT . $result;
    }

    public function getImages(): array
    {
        return $this->images;
    }
}
