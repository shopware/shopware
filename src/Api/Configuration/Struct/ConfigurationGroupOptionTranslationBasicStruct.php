<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Entity;



class ConfigurationGroupOptionTranslationBasicStruct extends Entity
{

    /**
     * @var string
     */
    protected $configurationGroupOptionId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $languageVersionId;

    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $name;


    public function getConfigurationGroupOptionId(): string
    {
        return $this->configurationGroupOptionId;
    }

    public function setConfigurationGroupOptionId(string $configurationGroupOptionId): void
    {
        $this->configurationGroupOptionId = $configurationGroupOptionId;
    }


    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }


    public function getLanguageVersionId(): string
    {
        return $this->languageVersionId;
    }

    public function setLanguageVersionId(string $languageVersionId): void
    {
        $this->languageVersionId = $languageVersionId;
    }


    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

}