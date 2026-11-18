<?php
/**
 * _seopanel — поле ключевой фразы и панель SEO-анализа.
 *
 * Переменные: $fieldName, $fieldId, $label, $comment, $value, $panelId,
 * $handler, $analysis.
 */
$value = (string) ($value ?? '');
$panelId = (string) ($panelId ?? '');
?>
<div
    class="field-seopanel"
    data-seopanel
    data-seo-handler="<?= e($handler) ?>"
    data-seo-panel="<?= e($panelId) ?>"
>
    <div class="form-group">
        <?php if ($label !== ''): ?>
            <label class="control-label" for="<?= e($fieldId) ?>"><?= e($label) ?></label>
        <?php endif ?>
        <input
            type="text"
            id="<?= e($fieldId) ?>"
            name="<?= e($fieldName) ?>"
            value="<?= e($value) ?>"
            class="form-control"
            autocomplete="off"
            data-seo-source="keyphrase"
            placeholder="например: как выбрать книгу"
        >
        <?php if ($comment !== ''): ?>
            <p class="help-block"><?= e($comment) ?></p>
        <?php endif ?>
    </div>

    <div class="seo-panel">
        <div class="seo-panel__head">
            <span class="seo-panel__heading">Предпросмотр сниппета</span>
            <span class="seo-panel__counter" data-seo-counter="title">0 / 60</span>
            <span class="seo-panel__counter" data-seo-counter="description">0 / 155</span>
        </div>

        <div class="seo-panel__serp">
            <div class="seo-panel__serp-url" data-seo-preview="url"></div>
            <div class="seo-panel__serp-title" data-seo-preview="title"></div>
            <div class="seo-panel__serp-description" data-seo-preview="description"></div>
        </div>

        <div class="seo-panel__body" id="<?= e($panelId) ?>">
            <?= $this->makePartial('analysis') ?>
        </div>
    </div>
</div>
