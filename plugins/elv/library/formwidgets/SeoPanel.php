<?php namespace Elv\Library\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Elv\Library\Classes\SeoAnalyzer;

/**
 * SeoPanel — поле ключевой фразы с панелью SEO-анализа.
 *
 * Поле хранит `seo_focus_keyphrase`, а под ним показывается сниппет и
 * светофор по правилам `SeoAnalyzer`. Анализ считается на сервере: при
 * открытии записи и после каждого изменения полей через AJAX-обработчик
 * `onAnalyze`, чтобы правила не дублировались в JS.
 */
class SeoPanel extends FormWidgetBase
{
    /**
     * @var string defaultAlias
     */
    public $defaultAlias = 'seopanel';

    /**
     * loadAssets подключает стили и скрипт панели.
     */
    protected function loadAssets()
    {
        $this->addCss('css/seopanel.css');
        $this->addJs('js/seopanel.js');
    }

    /**
     * render выводит поле и панель анализа.
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('seopanel');
    }

    /**
     * prepareVars готовит переменные шаблона.
     */
    public function prepareVars()
    {
        $this->vars['fieldName'] = $this->getFieldName();
        $this->vars['fieldId'] = $this->getId();
        $this->vars['label'] = (string) ($this->formField->label ?? '');
        $this->vars['comment'] = (string) ($this->formField->comment ?? '');
        $this->vars['value'] = (string) $this->getLoadValue();
        $this->vars['panelId'] = $this->getId('panel');
        $this->vars['handler'] = $this->getEventHandler('onAnalyze');
        $this->vars['analysis'] = $this->analyze($this->recordData());
    }

    /**
     * onAnalyze пересчитывает анализ по текущим значениям формы.
     *
     * Поля приходят из JS плоским списком: несохранённые значения в форме
     * нельзя прочитать из модели.
     */
    public function onAnalyze()
    {
        if ($this->previewMode) {
            return '';
        }

        $this->vars['analysis'] = $this->analyze([
            'title' => (string) post('title', ''),
            'seo_title' => (string) post('seo_title', ''),
            'seo_description' => (string) post('seo_description', ''),
            'slug' => (string) post('slug', ''),
            'keyphrase' => (string) post('keyphrase', ''),
            'content' => (string) post('content', ''),
        ]);

        return $this->makePartial('analysis');
    }

    /**
     * analyze прогоняет данные через анализатор.
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function analyze(array $data): array
    {
        return (new SeoAnalyzer())->analyze($data);
    }

    /**
     * recordData — сохранённые значения записи для первого рендера.
     *
     * @return array<string, string>
     */
    protected function recordData(): array
    {
        return [
            'title' => $this->attribute('title'),
            'seo_title' => $this->attribute('seo_title'),
            'seo_description' => $this->attribute('seo_description'),
            'slug' => $this->attribute('slug'),
            'keyphrase' => (string) $this->getLoadValue(),
            'content' => $this->attribute('content'),
        ];
    }

    /**
     * attribute — значение атрибута модели, пустая строка если его нет.
     */
    protected function attribute(string $name): string
    {
        if (!$this->model) {
            return '';
        }

        $value = $this->model->getAttribute($name);

        return is_scalar($value) ? (string) $value : '';
    }
}
