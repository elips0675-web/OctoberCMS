<?php
/**
 * _analysis — тело панели SEO-анализа.
 *
 * Переменные: $analysis (результат SeoAnalyzer).
 */
$checks = $analysis['checks'] ?? [];
$stats = $analysis['stats'] ?? [];
$status = $analysis['status'] ?? 'ok';
?>
<div class="seo-panel__summary seo-panel__summary--<?= e($status) ?>">
    <span class="seo-panel__dot seo-panel__dot--<?= e($status) ?>"></span>
    <span class="seo-panel__verdict"><?= e($analysis['label'] ?? '') ?></span>
    <span class="seo-panel__stats">
        <?= e((string) ($stats['words'] ?? 0)) ?> слов ·
        <?= e((string) ($stats['minutes'] ?? 1)) ?> мин чтения ·
        ключ <?= e((string) ($stats['density'] ?? 0)) ?> %
    </span>
</div>

<ul class="seo-panel__checks">
    <?php foreach ($checks as $check): ?>
        <li class="seo-panel__check seo-panel__check--<?= e($check['status']) ?>">
            <span class="seo-panel__mark"></span>
            <span class="seo-panel__check-title"><?= e($check['title']) ?></span>
            <span class="seo-panel__check-value"><?= e($check['value']) ?></span>
            <?php if ($check['status'] !== 'good' && $check['hint'] !== ''): ?>
                <span class="seo-panel__check-hint"><?= e($check['hint']) ?></span>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
