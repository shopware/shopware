<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class RenderedDocument
{
    const GLOBAL_STYLE_CONTENT = <<<EOD
<style type="text/css">

dl dt {
    font-weight: bolder;
    margin-top: 1rem;
}

dl dd {
    padding-left: 2rem;
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

        return self::GLOBAL_STYLE_CONTENT . $result;
    }

    public function getImages(): array
    {
        return $this->images;
    }
}
