<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product\Fixture;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class ProductsFixture
{
    /**
     * @return list<array<mixed>>
     */
    public static function get(IdsCollection $ids, string $secondLanguage, string $thirdLanguage): array
    {
        return [
            (new ProductBuilder($ids, 'product-1'))
                ->name('Silk')
                ->category('navi')
                ->customField('testField', 'Silk')
                ->visibility()
                ->tax('t1')
                ->manufacturer('m1')
                ->price(50, 50, 'default', 150, 150)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->createdAt('2019-01-01 10:11:00')
                ->category('c1')
                ->category('c2')
                ->property('red', 'color')
                ->property('xl', 'size')
                ->customField('test_int', 19999)
                ->customField('test_date', (new \DateTime())->format('Y-m-d H:i:s'))
                ->customField('testFloatingField', 1.5)
                ->customField('test_bool', true)
                ->build(),
            (new ProductBuilder($ids, 'product-2'))
                ->name('Rubber')
                ->category('navi')
                ->customField('testField', 'Rubber')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100, 100, 'default', 150, 150)
                ->price(300, null, 'anotherCurrency')
                ->releaseDate('2019-01-01 10:13:00')
                ->createdAt('2019-01-02 10:11:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('c1')
                ->property('green', 'color')
                ->property('l', 'size')
                ->customField('test_int', 200)
                ->customField('test_date', (new \DateTime('2000-01-01'))->format('Y-m-d H:i:s'))
                ->customField('testFloatingField', 1) // Without the casting in formatCustomFields this fails
                ->build(),
            (new ProductBuilder($ids, 'product-3'))
                ->name('Stilk')
                ->category('navi')
                ->customField('testField', 'Stilk')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(150, 150, 'default', 150, 150)
                ->price(800, null, 'anotherCurrency')
                ->releaseDate('2019-06-15 13:00:00')
                ->purchasePrice(100)
                ->stock(100)
                ->category('c1')
                ->category('c3')
                ->property('red', 'color')
                ->build(),
            (new ProductBuilder($ids, 'zanother-product-3b'))
                ->name('Bar Sti')
                ->manufacturer('m2')
                ->price(100, 100, 'default', 100, 100)
                ->purchasePrice(100)
                ->stock(100)
                ->property('silver', 'color')
                ->build(),
            (new ProductBuilder($ids, 'product-4'))
                ->name('Grouped 1')
                ->category('navi')
                ->customField('testField', 'Grouped 1')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(200, 200, 'default', 500, 500)
                ->price(500, null, 'anotherCurrency')
                ->releaseDate('2020-09-30 15:00:00')
                ->purchasePrice(100)
                ->stock(300)
                ->property('green', 'color')
                ->build(),
            (new ProductBuilder($ids, 'product-5'))
                ->name('Grouped 2')
                ->category('navi')
                ->customField('testField', 'Grouped 2')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(250, 250, 'default', 300, 300)
                ->price(600, null, 'anotherCurrency')
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(100)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'product-6'))
                ->name('Spachtelmasse of some awesome company')
                ->category('navi')
                ->customField('testField', 'Spachtelmasse')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->price(200, null, 'anotherCurrency')
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'product-7'))
                ->name('Test Product for Timezone ReleaseDate')
                ->category('navi')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->price(300)
                ->releaseDate('2024-12-11 23:59:00')
                ->stock(350)
                ->build(),
            (new ProductBuilder($ids, 'n7'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'n8'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'n9'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'n10'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 'n11'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($ids, 's1'))
                ->name('aa')
                ->category('navi')
                ->customField('testField', 'aa')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 's2'))
                ->name('Aa')
                ->category('navi')
                ->customField('testField', 'Aa')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 's3'))
                ->name('AA')
                ->category('navi')
                ->customField('testField', 'AA')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 's4'))
                ->name('Ba')
                ->category('navi')
                ->customField('testField', 'Ba')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 's5'))
                ->name('BA')
                ->category('navi')
                ->customField('testField', 'BA')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 's6'))
                ->name('BB')
                ->category('navi')
                ->customField('testField', 'BB')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($ids, 'cf1'))
                ->name('CF')
                ->category('navi')
                ->customField(
                    'test_text',
                    'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Pellentesque. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. PellentesqueLorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Pellentesque. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero'
                )
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),

            // no rule = 70€
            (new ProductBuilder($ids, 'p.1'))
                ->price(70)
                ->price(99, null, 'currency')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->build(),

            // no rule = 79€
            (new ProductBuilder($ids, 'p.2'))
                ->price(80)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.2.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.2.2'))
                        ->price(79)
                        ->price(88, null, 'currency')
                        ->build()
                )
                ->build(),

            // no rule = 90€
            (new ProductBuilder($ids, 'p.3'))
                ->price(90)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.3.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.3.2'))
                        ->price(100)
                        ->build()
                )
                ->build(),

            // no rule = 60€
            (new ProductBuilder($ids, 'p.4'))
                ->price(100)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.4.1'))
                        ->price(60)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.4.2'))
                        ->price(70)
                        ->price(101, null, 'currency')
                        ->build()
                )
                ->build(),

            // no rule = 110€  ||  rule-a = 130€
            (new ProductBuilder($ids, 'p.5'))
                ->price(110)
                ->prices('rule-a', 130)
                ->prices('rule-a', 120, 'default', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->build(),

            // no rule = 120€  ||  rule-a = 130€
            (new ProductBuilder($ids, 'p.6'))
                ->price(120)
                ->prices('rule-a', 150)
                ->prices('rule-a', 140, 'default', null, 3)
                ->prices('rule-a', 199, 'currency')
                ->prices('rule-a', 188, 'currency', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.6.1'))
                        ->prices('rule-a', 140)
                        ->prices('rule-a', 130, 'default', null, 3)
                        ->prices('rule-a', 188, 'currency')
                        ->prices('rule-a', 177, 'currency', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.6.2'))
                        ->build()
                )
                ->build(),

            // no rule = 130€  ||   rule-a = 150€
            (new ProductBuilder($ids, 'p.7'))
                ->price(130)
                ->prices('rule-a', 150)
                ->prices('rule-a', 140, 'default', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.7.1'))
                        ->prices('rule-a', 160)
                        ->prices('rule-a', 150, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.7.2'))
                        ->build()
                )
                ->build(),

            // no rule = 140€  ||  rule-a = 170€
            (new ProductBuilder($ids, 'p.8'))
                ->price(140)
                ->prices('rule-a', 160)
                ->prices('rule-a', 150, 'default', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.8.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.8.2'))
                        ->prices('rule-a', 180)
                        ->prices('rule-a', 170, 'default', null, 3)
                        ->build()
                )
                ->build(),

            // no-rule = 150€   ||   rule-a  = 160€
            (new ProductBuilder($ids, 'p.9'))
                ->price(150)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.9.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.9.2'))
                        ->price(160)
                        ->build()
                )
                ->build(),

            // no rule = 150€  ||  rule-a = 150€
            (new ProductBuilder($ids, 'p.10'))
                ->price(160)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.10.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.10.2'))
                        ->price(150)
                        ->build()
                )
                ->build(),

            // no-rule = 170  || rule-a = 190  || rule-b = 200
            (new ProductBuilder($ids, 'p.11'))
                ->price(170)
                ->prices('rule-a', 190)
                ->prices('rule-a', 180, 'default', null, 3)
                ->prices('rule-b', 200)
                ->prices('rule-b', 190, 'default', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.11.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.11.2'))
                        ->build()
                )
                ->build(),

            // no rule = 180 ||  rule-a = 210  || rule-b = 180 || a+b = 210 || b+a = 210/190
            (new ProductBuilder($ids, 'p.12'))
                ->price(180)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'v.12.1'))
                        ->prices('rule-a', 220)
                        ->prices('rule-a', 210, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.12.2'))
                        ->prices('rule-a', 210)
                        ->prices('rule-a', 200, 'default', null, 3)
                        ->prices('rule-b', 200)
                        ->prices('rule-b', 190, 'default', null, 3)
                        ->build()
                )
                ->build(),

            // no rule = 190 ||  rule-a = 220  || rule-b = 190 || a+b = 220 || b+a = 220/200
            (new ProductBuilder($ids, 'p.13'))
                ->price(190)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->prices('rule-a', 230)
                ->prices('rule-a', 220, 'default', null, 3)
                ->variant(
                    (new ProductBuilder($ids, 'v.13.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'v.13.2'))
                        ->prices('rule-a', 220)
                        ->prices('rule-a', 210, 'default', null, 3)
                        ->prices('rule-b', 210)
                        ->prices('rule-b', 200, 'default', null, 3)
                        ->build()
                )
                ->build(),

            (new ProductBuilder($ids, 'dal-1'))
                ->name('Default')
                ->category('navi')
                ->customField('testField', 'Silk')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m1')
                ->price(50)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->category('c1')
                ->category('c2')
                ->property('red', 'color')
                ->property('xl', 'size')
                ->add('weight', 12.3)
                ->add('height', 9.3)
                ->add('width', 1.3)
                ->translation($secondLanguage, 'name', 'Second')
                ->translation($thirdLanguage, 'name', 'Third')
                ->build(),

            (new ProductBuilder($ids, 'dal-2'))
                ->name('Default')
                ->category('pants')
                ->customField('testField', 'Silk')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m1')
                ->price(60)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->category('c1')
                ->category('c2')
                ->property('red', 'color')
                ->property('xl', 'size')
                ->add('weight', 12.3)
                ->add('height', 9.3)
                ->add('width', 1.3)
                ->translation($secondLanguage, 'name', 'Second')
                ->translation($thirdLanguage, 'name', 'Third')
                ->variant(
                    (new ProductBuilder($ids, 'dal-2.1'))
                        ->translation($secondLanguage, 'name', 'Variant 1 Second')
                        ->translation($secondLanguage, 'description', 'Variant 1 Second Desc')
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'dal-2.2'))
                        ->translation($secondLanguage, 'name', null)
                        ->translation($secondLanguage, 'description', 'Variant 2 Second Desc')
                        ->translation($thirdLanguage, 'name', 'Variant 2 Third')
                        ->translation($thirdLanguage, 'description', 'Variant 2 Third Desc')
                        ->build()
                )
                ->build(),
            (new ProductBuilder($ids, 'dal-3'))
                ->price(50)
                ->customField('a', '1')
                ->translation($secondLanguage, 'customFields', ['a' => '2', 'b' => '1'])
                ->translation($thirdLanguage, 'customFields', ['a' => '3', 'b' => '2', 'c' => '1'])
                ->build(),

            (new ProductBuilder($ids, 's-1'))
                ->name('Default-1')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
                ->build(),
            (new ProductBuilder($ids, 's-2'))
                ->name('Default-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_LINK)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_SEARCH)
                ->build(),
            (new ProductBuilder($ids, 's-3'))
                ->name('Default-3')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_SEARCH)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_LINK)
                ->build(),
            (new ProductBuilder($ids, 's-4'))
                ->name('Default-4')
                ->price(1)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_ALL)
                ->add('downloads', [
                    [
                        'media' => [
                            'fileName' => 'foo',
                            'fileExtension' => 'bar',
                            'private' => true,
                        ],
                    ],
                ])
                ->build(),
            (new ProductBuilder($ids, 'variant-1'))
                ->name('Main-Product-1')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'variant-1.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'variant-1.2'))
                        ->build()
                )
                ->build(),
            (new ProductBuilder($ids, 'variant-2'))
                ->name('Main-Product-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($ids, 'variant-2.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'variant-2.2'))
                        ->build()
                )
                ->build(),
            (new ProductBuilder($ids, 'variant-3'))
                ->name('Main-Product-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->customField('test_int', 8000000000)
                ->variant(
                    (new ProductBuilder($ids, 'variant-3.1'))
                        ->customField('random', 1)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'variant-3.2'))
                        ->customField('random', 1)
                        ->build()
                )
                ->build(),
            (new ProductBuilder($ids, 'sort.glumanda'))
                ->tag('shopware')
                ->price(1)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'sort.bisasam'))
                ->tag('amazon')
                ->price(1)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'sort.pikachu'))
                ->tag('zalando')
                ->price(1)
                ->visibility()
                ->build(),
        ];
    }
}
