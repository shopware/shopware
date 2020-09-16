<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

class ChangelogDefinition
{
    /** @var string */
    private $title;

    /** @var string */
    private $issue;

    /** @var string|null */
    private $flag;

    /** @var string|null */
    private $author;

    /** @var string|null */
    private $authorEmail;

    /** @var string|null */
    private $authorGitHub;

    /** @var string|null */
    private $core;

    /** @var string|null */
    private $storefront;

    /** @var string|null */
    private $administration;

    /** @var string|null */
    private $api;

    /** @var string|null */
    private $upgrade;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): ChangelogDefinition
    {
        $this->title = $title;

        return $this;
    }

    public function getIssue(): string
    {
        return $this->issue;
    }

    public function setIssue(string $issue): ChangelogDefinition
    {
        $this->issue = $issue;

        return $this;
    }

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(?string $flag): ChangelogDefinition
    {
        $this->flag = $flag;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): ChangelogDefinition
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): ChangelogDefinition
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getAuthorGitHub(): ?string
    {
        return $this->authorGitHub;
    }

    public function setAuthorGitHub(?string $authorGitHub): ChangelogDefinition
    {
        $this->authorGitHub = $authorGitHub;

        return $this;
    }

    public function getCore(): ?string
    {
        return $this->core;
    }

    public function setCore(?string $core): ChangelogDefinition
    {
        $this->core = $core;

        return $this;
    }

    public function getStorefront(): ?string
    {
        return $this->storefront;
    }

    public function setStorefront(?string $storefront): ChangelogDefinition
    {
        $this->storefront = $storefront;

        return $this;
    }

    public function getAdministration(): ?string
    {
        return $this->administration;
    }

    public function setAdministration(?string $administration): ChangelogDefinition
    {
        $this->administration = $administration;

        return $this;
    }

    public function getApi(): ?string
    {
        return $this->api;
    }

    public function setApi(?string $api): ChangelogDefinition
    {
        $this->api = $api;

        return $this;
    }

    public function getUpgradeInformation(): ?string
    {
        return $this->upgrade;
    }

    public function setUpgradeInformation(?string $upgrade): ChangelogDefinition
    {
        $this->upgrade = $upgrade;

        return $this;
    }

    public function isValid(): bool
    {
        // These are required fields in a changelog
        if (empty($this->title) || empty($this->issue)) {
            return false;
        }

        // At least one of them have to be specified
        if (empty($this->api) && empty($this->storefront) && empty($this->administration) && empty($this->core)) {
            return false;
        }

        return true;
    }

    public function toTemplate(): string
    {
        $template = <<<EOD
---
title: $this->title
issue: $this->issue
%FEATURE_FLAG%
%AUTHOR%
%AUTHOR_EMAIL% 
%AUTHOR_GITHUB%
---
# Core
*  
___
# API
*  
___
# Administration
*  
___
# Storefront
*  
___
# Upgrade Information
## Topic 1
### Topic 1a
### Topic 1b
## Topic 2
EOD;
        $template = str_replace('%FEATURE_FLAG%', ($this->flag ? 'flag: ' . $this->flag : ''), $template);
        $template = str_replace('%AUTHOR%', ($this->author ? 'author: ' . $this->author : ''), $template);
        $template = str_replace('%AUTHOR_EMAIL%', ($this->authorEmail ? 'author_email: ' . $this->authorEmail : ''), $template);
        $template = str_replace('%AUTHOR_GITHUB%', ($this->authorGitHub ? 'author_github: ' . $this->authorGitHub : ''), $template);
        $template = str_replace("\n\n", "\n", $template);

        return trim($template);
    }
}
