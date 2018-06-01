<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Struct;

use Shopware\System\Touchpoint\Struct\TouchpointBasicStruct;

class SnippetDetailStruct extends SnippetBasicStruct
{
    /**
     * @var TouchpointBasicStruct
     */
    protected $touchpoint;

    public function getTouchpoint(): TouchpointBasicStruct
    {
        return $this->touchpoint;
    }

    public function setTouchpoint(TouchpointBasicStruct $touchpoint): void
    {
        $this->touchpoint = $touchpoint;
    }
}
