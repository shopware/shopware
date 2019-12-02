<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use function current;
use Exception;
use function next;
use function prev;
use Shopware\Recovery\Install\Service\TranslationService;
use Slim\App;

class MenuHelper
{
    /**
     * @var App
     */
    private $slim;

    /**
     * @var string[]
     */
    private $entries;

    /**
     * @var TranslationService
     */
    private $translator;

    public function __construct(App $slim, TranslationService $translator, array $entries)
    {
        $this->entries = $entries;
        $this->slim = $slim;
        $this->translator = $translator;
    }

    public function printMenu(): void
    {
        $result = [];
        $complete = true;
        foreach ($this->entries as $entry) {
            $active = ($entry === current($this->entries));
            if ($active) {
                $complete = false;
            }

            $key = 'menuitem_' . $entry;
            $label = $this->translator->translate($key);

            $result[] = [
                'label' => $label,
                'complete' => $complete,
                'active' => $active,
            ];
        }

        echo $this->slim->getContainer()->get('renderer')->fetch('/_menu.php', ['entries' => $result]);
    }

    /**
     * @param string $name
     *
     * @throws Exception
     */
    public function setCurrent($name): void
    {
        if (!in_array($name, $this->entries, true)) {
            throw new Exception('could not find entrie');
        }

        reset($this->entries);
        while ($name !== current($this->entries)) {
            next($this->entries);
        }
    }

    public function getNextUrl(array $params = []): string
    {
        $entries = $this->entries;
        $currentEntry = next($entries);

        return $this->slim->getContainer()->get('router')->pathFor($currentEntry, $params);
    }

    public function getPreviousUrl(array $params = []): string
    {
        $entries = $this->entries;
        $currentEntry = prev($entries);

        return $this->slim->getContainer()->get('router')->pathFor($currentEntry, $params);
    }

    public function getCurrentUrl(array $data = [], array $queryParams = []): string
    {
        $entries = $this->entries;
        $currentEntry = current($entries);

        return $this->slim->getContainer()->get('router')->pathFor($currentEntry, $data, $queryParams);
    }
}
