<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\System\Touchpoint\Struct\TouchpointBasicStruct;

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
