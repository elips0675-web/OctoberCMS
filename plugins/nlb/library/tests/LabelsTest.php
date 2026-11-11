<?php namespace Nlb\Library\Tests;

use Nlb\Library\Classes\Labels;
use TestCase;

/**
 * LabelsTest — подписи свойств и цвета карточек.
 *
 * Значения сверены с `db/config/iblocks.php` источника: неизвестный код
 * не превращается в пустоту, а отдаёт нейтральную подпись.
 */
class LabelsTest extends TestCase
{
    public function testTags()
    {
        $this->assertSame('Книги', Labels::tag('books'));
        $this->assertSame('Дети', Labels::tag('kids'));
        $this->assertSame('unknown', Labels::tag('unknown'));
        $this->assertSame('', Labels::tag(null));
    }

    public function testEventCategory()
    {
        $this->assertSame('Литература', Labels::eventCategory('literature'));
        $this->assertSame('Для детей', Labels::eventCategory('children'));
        $this->assertSame('Технологии', Labels::eventCategory('tech'));
        $this->assertSame('Лекции', Labels::eventCategory('lectures'));
        $this->assertSame('Событие', Labels::eventCategory('unknown'));
    }

    public function testEventGradient()
    {
        $this->assertSame('bg-grad-sky', Labels::eventGradient('literature'));
        $this->assertSame('bg-grad-pink', Labels::eventGradient('children'));
        $this->assertSame('bg-grad-green', Labels::eventGradient('tech'));
        $this->assertSame('bg-grad-sky', Labels::eventGradient('unknown'));
    }

    public function testEventCategoriesOrder()
    {
        $this->assertSame(
            ['literature', 'children', 'tech', 'lecture'],
            array_keys(Labels::eventCategories())
        );
        $this->assertSame('Лекции', Labels::eventCategories()['lecture']);
    }

    public function testFormat()
    {
        $this->assertSame('Офлайн', Labels::format('offline'));
        $this->assertSame('Онлайн', Labels::format('online'));
        $this->assertSame('Гибрид', Labels::format('hybrid'));
        $this->assertSame('unknown', Labels::format('unknown'));
    }

    public function testAvailability()
    {
        $this->assertSame(['label' => 'В наличии', 'class' => 'is-available'], Labels::availability('available'));
        $this->assertSame(['label' => 'Под заказ', 'class' => 'is-reserved'], Labels::availability('reserved'));
        $this->assertSame(
            ['label' => 'Нет в наличии', 'class' => 'is-unavailable'],
            Labels::availability('unknown')
        );
    }

    public function testAge()
    {
        $this->assertSame('Всем', Labels::age('all'));
        $this->assertSame('Детям', Labels::age('kids'));
        $this->assertSame('Взрослым', Labels::age('adult'));
        $this->assertSame('unknown', Labels::age('unknown'));
    }
}
