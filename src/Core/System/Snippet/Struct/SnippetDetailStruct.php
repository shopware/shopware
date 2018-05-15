<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Struct;

use Shopware\Api\Application\Struct\ApplicationBasicStruct;

class SnippetDetailStruct extends SnippetBasicStruct
{
    /**
     * @var ApplicationBasicStruct
     */
    protected $application;

    public function getApplication(): ApplicationBasicStruct
    {
        return $this->application;
    }

    public function setApplication(ApplicationBasicStruct $application): void
    {
        $this->application = $application;
    }
}
