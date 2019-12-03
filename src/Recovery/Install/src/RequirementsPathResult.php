<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

class RequirementsPathResult
{
    /**
     * @var array
     */
    private $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function toArray(): array
    {
        return $this->result;
    }

    public function hasError(): bool
    {
        foreach ($this->result as $entry) {
            if (!$entry['existsAndWriteable']) {
                return true;
            }
        }

        return false;
    }
}
