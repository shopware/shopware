<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @internal
 */
#[Package('core')]
class ChangelogDefinition
{
    private const VIOLATION_MESSAGE_SECTION_SEPARATOR = 'You should use "___" to separate %s and %s section';
    private const VIOLATION_MESSAGE_STARTING_KEYWORD = "Changelog entry \"%s\" does not start with a valid keyword.\nPlease have look at the handbook: https://handbook.shopware.com/Product/Guides/Development/WritingChangelog#changelog-entries";

    #[Assert\NotBlank(message: 'The title should not be blank')]
    private string $title;

    #[Assert\NotBlank(message: 'The Jira ticket should not be blank')]
    #[Assert\Regex(pattern: '/^NEXT-\d+$/', message: 'The Jira ticket has an invalid format')]
    private string $issue;

    private ?string $flag = null;

    private ?string $author = null;

    private ?string $authorEmail = null;

    private ?string $authorGitHub = null;

    private ?string $core = null;

    private ?string $storefront = null;

    private ?string $administration = null;

    private ?string $api = null;

    private ?string $upgrade = null;

    private ?string $nextMajorVersionChanges = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (empty($this->api) && empty($this->core) && empty($this->storefront) && empty($this->administration)) {
            $context->buildViolation('You have to define at least one change of API, Core, Administration or Storefront')
                ->addViolation();
        }

        if ($this->api) {
            if (preg_match('/\n+#\s+(\w+)/', $this->api, $matches)) {
                $this->buildViolationSectionSeparator($context, ChangelogSection::api, $matches[1]);
            }
            $this->checkChangelogEntries($context, $this->api, ChangelogSection::api);
        }

        if ($this->storefront) {
            if (preg_match('/\n+#\s+(\w+)/', $this->storefront, $matches)) {
                $this->buildViolationSectionSeparator($context, ChangelogSection::storefront, $matches[1]);
            }
            $this->checkChangelogEntries($context, $this->storefront, ChangelogSection::storefront);
        }

        if ($this->administration) {
            if (preg_match('/\n+#\s+(\w+)/', $this->administration, $matches)) {
                $this->buildViolationSectionSeparator($context, ChangelogSection::administration, $matches[1]);
            }
            $this->checkChangelogEntries($context, $this->administration, ChangelogSection::administration);
        }

        if ($this->core) {
            if (preg_match('/\n+#\s+(\w+)/', $this->core, $matches)) {
                $this->buildViolationSectionSeparator($context, ChangelogSection::core, $matches[1]);
            }
            $this->checkChangelogEntries($context, $this->core, ChangelogSection::core);
        }

        if ($this->upgrade && preg_match('/\n+#\s+(\w+)/', $this->upgrade, $matches)) {
            $this->buildViolationSectionSeparator($context, ChangelogSection::upgrade, $matches[1]);
        }

        if ($this->nextMajorVersionChanges && preg_match('/\n+#\s+(\w+)/', $this->nextMajorVersionChanges, $matches)) {
            $this->buildViolationSectionSeparator($context, ChangelogSection::major, $matches[1]);
        }

        if ($this->flag && !Feature::has($this->flag)) {
            $context->buildViolation(sprintf('Unknown flag %s is assigned ', $this->flag))
                ->atPath('flag')
                ->addViolation();
        }
    }

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

    public function setNextMajorVersionChanges(?string $nextMajorVersionChanges): ChangelogDefinition
    {
        $this->nextMajorVersionChanges = $nextMajorVersionChanges;

        return $this;
    }

    public function getNextMajorVersionChanges(): ?string
    {
        return $this->nextMajorVersionChanges;
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
___
# Next Major Version Changes
## Breaking Change 1:
* Do this
## Breaking Change 2:
change
```
static
```
to
```
self
```
EOD;
        $template = str_replace('%FEATURE_FLAG%', ($this->flag ? 'flag: ' . $this->flag : ''), $template);
        $template = str_replace('%AUTHOR%', ($this->author ? 'author: ' . $this->author : ''), $template);
        $template = str_replace('%AUTHOR_EMAIL%', ($this->authorEmail ? 'author_email: ' . $this->authorEmail : ''), $template);
        $template = str_replace('%AUTHOR_GITHUB%', ($this->authorGitHub ? 'author_github: ' . $this->authorGitHub : ''), $template);
        $template = str_replace("\n\n", "\n", $template);

        return trim($template);
    }

    private function buildViolationSectionSeparator(ExecutionContextInterface $context, ChangelogSection $currentSection, string $nextSection): void
    {
        $context->buildViolation(sprintf(self::VIOLATION_MESSAGE_SECTION_SEPARATOR, $currentSection->value, $nextSection))
            ->atPath($currentSection->name)
            ->addViolation();
    }

    private function checkChangelogEntries(ExecutionContextInterface $context, ?string $changelogPart, ChangelogSection $currentSection): void
    {
        if (!\is_string($changelogPart)) {
            return;
        }

        $changelogEntries = explode("\n", $changelogPart);
        foreach ($changelogEntries as $changelogEntry) {
            if (!str_starts_with($changelogEntry, '*')) {
                continue;
            }
            // Remove leading asterisk and spaces around changelog entry
            $changelogEntry = trim(substr($changelogEntry, 1));
            if ($changelogEntry === '') {
                continue;
            }

            foreach (ChangelogKeyword::cases() as $allowedChangelogKeyword) {
                if (str_starts_with($changelogEntry, $allowedChangelogKeyword->value)) {
                    continue 2;
                }
            }
            $context->buildViolation(sprintf(self::VIOLATION_MESSAGE_STARTING_KEYWORD, $changelogEntry))
                ->atPath($currentSection->name)
                ->addViolation();
        }
    }
}
