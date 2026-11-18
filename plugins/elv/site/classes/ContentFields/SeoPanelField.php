<?php namespace Elv\Site\Classes\ContentFields;

use Tailor\ContentFields\GenericField;
use October\Contracts\Element\FormElement;

/**
 * SeoPanelField — поле Tailor «ключевая фраза + панель анализа».
 *
 * Колонку создаёт GenericField (тип text), а в форме поле рисует виджет
 * `SeoPanel`: он же выводит светофор и предпросмотр сниппета.
 */
class SeoPanelField extends GenericField
{
    /**
     * defineFormField подключает виджет панели вместо простого input.
     */
    public function defineFormField(FormElement $form, $context = null)
    {
        $form->addFormField($this->fieldName, $this->label)
            ->displayAs('elvseopanel')
            ->useConfig($this->config);
    }
}
