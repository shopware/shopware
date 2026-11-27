<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldLabelSearchRankingTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const TERM = 'Material thickness';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $suffix = Uuid::randomHex();

        static::getContainer()->get('custom_field_set.repository')->create([
            [
                'id' => $this->ids->get('set'),
                'name' => 'cf_ranking_set_' . $suffix,
                'customFields' => [
                    ['id' => $this->ids->get('exact'), 'name' => 'cf_exact_' . $suffix, 'type' => 'bool', 'config' => ['label' => ['de-DE' => 'Material thickness']]],
                    ['id' => $this->ids->get('contains'), 'name' => 'cf_contains_' . $suffix, 'type' => 'int', 'config' => ['label' => ['en-GB' => 'Wall Material thickness']]],
                    ['id' => $this->ids->get('one-word'), 'name' => 'cf_one_word_' . $suffix, 'type' => 'text', 'config' => ['label' => ['de-DE' => 'Sheet thickness']]],
                    ['id' => $this->ids->get('no-word'), 'name' => 'cf_no_word_' . $suffix, 'type' => 'text', 'config' => ['label' => ['de-DE' => 'Colour', 'en-GB' => 'Colour']]],
                    ['id' => $this->ids->get('other-locale'), 'name' => 'cf_other_locale_' . $suffix, 'type' => 'text', 'config' => ['label' => ['nl-NL' => 'Material thickness']]],
                ],
            ],
        ], Context::createDefaultContext());
    }

    public function testModalCriteriaRanksLabelMatchesAboveTheTypeSorting(): void
    {
        $queries = [];
        foreach (['name', 'type', 'config.label.de-DE', 'config.label.en-GB'] as $field) {
            $queries[] = ['score' => 500, 'query' => ['type' => 'equals', 'field' => $field, 'value' => self::TERM]];
            $queries[] = ['score' => 250, 'query' => ['type' => 'contains', 'field' => $field, 'value' => self::TERM]];
            $queries[] = ['score' => 80, 'query' => ['type' => 'contains', 'field' => $field, 'value' => 'Material']];
            $queries[] = ['score' => 80, 'query' => ['type' => 'contains', 'field' => $field, 'value' => 'thickness']];
        }

        $foundIds = $this->searchCustomFieldIds([
            'page' => 1,
            'limit' => 10,
            'query' => $queries,
            'sort' => [
                ['field' => '_score', 'order' => 'DESC', 'naturalSorting' => false],
                ['field' => 'type', 'order' => 'DESC', 'naturalSorting' => false],
            ],
            'total-count-mode' => 1,
        ]);

        static::assertSame(
            [$this->ids->get('exact'), $this->ids->get('contains'), $this->ids->get('one-word')],
            array_values(array_intersect($foundIds, $this->ids->all())),
        );
    }

    public function testTermOnlyCriteriaDoesNotFindTheFieldsByTheirLabel(): void
    {
        $foundIds = $this->searchCustomFieldIds([
            'page' => 1,
            'limit' => 10,
            'term' => self::TERM,
            'sort' => [
                ['field' => 'type', 'order' => 'DESC', 'naturalSorting' => false],
            ],
            'total-count-mode' => 1,
        ]);

        static::assertSame([], array_values(array_intersect($foundIds, $this->ids->all())));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function searchCustomFieldIds(array $payload): array
    {
        $this->getBrowser()->jsonRequest('POST', '/api/search/custom-field', $payload);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertIsArray($content['data']);

        return array_column($content['data'], 'id');
    }
}
