<?php namespace Elv\Library\Classes;

/**
 * SeoAnalyzer — проверка записи по правилам SEO-гайда.
 *
 * Одиннадцать проверок «светофора» без внешних сервисов и без морфологии:
 * длина и наличие ключа в мета-полях, плотность фразы, структура заголовков,
 * длина предложений и абзацев, пассив, переходные слова, ссылки и alt.
 * Анализ считается на сервере, чтобы правила жили в одном месте и были
 * покрыты PHPUnit, а панель в админке только показывает результат.
 */
class SeoAnalyzer
{
    public const GOOD = 'good';
    public const OK = 'ok';
    public const BAD = 'bad';

    /**
     * Границы длины заголовка и описания — по гайду 2026 года.
     */
    public const TITLE_MIN = 40;
    public const TITLE_MAX = 60;
    public const DESCRIPTION_MIN = 120;
    public const DESCRIPTION_MAX = 155;

    /**
     * Плотность ключевой фразы, % от объёма текста.
     */
    public const DENSITY_MIN = 0.75;
    public const DENSITY_MAX = 3.0;

    /**
     * Служебные границы читаемости.
     */
    public const LONG_SENTENCE_WORDS = 20;
    public const SUBHEADING_WORDS = 300;
    public const LONG_PARAGRAPH_WORDS = 120;

    /**
     * @var array<int, string> переходные слова из гайда
     */
    protected $transitionWords = [
        'однако', 'следовательно', 'кроме того', 'таким образом', 'во-первых',
        'во-вторых', 'например', 'поэтому', 'значит', 'итак', 'впрочем', 'кстати',
        'в итоге', 'затем', 'далее', 'при этом', 'вместе с тем', 'тем не менее',
        'в частности', 'к тому же', 'более того', 'наконец', 'в результате',
    ];

    /**
     * @var array<int, string> маркеры пассива и безличных оборотов
     */
    protected $passiveMarkers = [
        'был', 'была', 'было', 'были', 'будет', 'будут', 'быть',
        'является', 'являются', 'являлся', 'являлась',
        'делается', 'делаются', 'проводится', 'проводятся',
        'считается', 'считаются', 'используется', 'используются',
    ];

    /**
     * analyze проверяет запись и возвращает светофор со списком проверок.
     *
     * Вход: title, seo_title, seo_description, slug, keyphrase, content.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $seoTitle = trim((string) ($data['seo_title'] ?? ''));
        $description = trim((string) ($data['seo_description'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $keyphrase = trim((string) ($data['keyphrase'] ?? ''));
        $content = (string) ($data['content'] ?? '');

        $effectiveTitle = $seoTitle !== '' ? $seoTitle : $title;

        $plain = $this->plainText($content);
        $words = $this->wordCount($plain);
        $sentences = $this->sentences($plain);
        $headings = $this->headings($content);
        $links = $this->links($content);
        $images = $this->images($content);
        $density = $this->density($plain, $keyphrase);

        $checks = [];

        $checks[] = $this->check(
            'keyphrase_set',
            $keyphrase === '' ? self::OK : self::GOOD,
            'Ключевая фраза',
            $keyphrase === '' ? 'не задана' : $keyphrase,
            'Задайте одну фразу, под которую пишется запись.'
        );

        $checks[] = $this->check(
            'keyphrase_in_title',
            $this->containsKeyphrase($effectiveTitle, $keyphrase) ? self::GOOD : self::BAD,
            'Ключ в заголовке',
            $seoTitle === '' ? 'взят из названия' : 'SEO-заголовок',
            'Впишите фразу в SEO-заголовок — поисковик показывает его первым.'
        );

        $checks[] = $this->check(
            'keyphrase_in_description',
            $this->containsKeyphrase($description, $keyphrase) ? self::GOOD : self::BAD,
            'Ключ в описании',
            $description === '' ? 'описание пустое' : 'в описании',
            'Добавьте фразу в описание: оно попадает в сниппет.'
        );

        $checks[] = $this->check(
            'keyphrase_in_slug',
            $this->slugContainsKeyphrase($slug, $keyphrase) ? self::GOOD : self::BAD,
            'Ключ в адресе',
            $slug === '' ? 'адрес пуст' : '/' . ltrim($slug, '/') . '/',
            'Транслит фразы должен быть в адресе страницы: /kak-vybrat-knigu/.'
        );

        $checks[] = $this->check(
            'keyphrase_in_intro',
            $this->containsKeyphrase($this->firstParagraph($content), $keyphrase) ? self::GOOD : self::BAD,
            'Ключ в первом абзаце',
            $words > 0 ? 'первый абзац' : 'текста нет',
            'Первое предложение должно содержать фразу и отвечать на вопрос читателя.'
        );

        $checks[] = $this->check(
            'keyphrase_density',
            $this->densityStatus($density, $keyphrase),
            'Плотность фразы',
            $keyphrase === '' ? '—' : $this->formatNumber($density) . ' %',
            'Норма от ' . self::DENSITY_MIN . ' до ' . self::DENSITY_MAX . ' %: без набора ключей.'
        );

        $checks[] = $this->check(
            'title_length',
            $this->rangeStatus(mb_strlen($effectiveTitle), self::TITLE_MIN, self::TITLE_MAX, 10, 5),
            'Длина заголовка',
            mb_strlen($effectiveTitle) . ' / ' . self::TITLE_MAX . ' симв.',
            'Уложите заголовок в ' . self::TITLE_MIN . '–' . self::TITLE_MAX . ' символов: длинный обрежется.'
        );

        $checks[] = $this->check(
            'description_length',
            $this->rangeStatus(mb_strlen($description), self::DESCRIPTION_MIN, self::DESCRIPTION_MAX, 20, 10),
            'Длина описания',
            mb_strlen($description) . ' / ' . self::DESCRIPTION_MAX . ' симв.',
            'Описание ' . self::DESCRIPTION_MIN . '–' . self::DESCRIPTION_MAX . ' символов: ключ и польза.'
        );

        $h1Count = $this->countHeadings($headings, 1);

        $checks[] = $this->check(
            'headings_h1',
            $h1Count === 0 ? self::GOOD : self::BAD,
            'Заголовок H1',
            'в тексте H1: ' . $h1Count,
            'Название записи уже выводится как H1 — внутри текста H1 не нужен.'
        );

        $checks[] = $this->check(
            'headings_levels',
            $this->levelsStatus($headings),
            'Порядок заголовков',
            $this->levelsLabel($headings),
            'Уровни не пропускаются: после H2 идёт H3, а не H4.'
        );

        $checks[] = $this->check(
            'headings_frequency',
            $this->subheadingStatus($words, $headings),
            'Подзаголовки',
            $words > 0 ? $this->formatNumber($words / max(1, count($headings))) . ' слов на подзаголовок' : '—',
            'Подзаголовок нужен примерно каждые ' . self::SUBHEADING_WORDS . ' слов: текст легче сканировать.'
        );

        $checks[] = $this->check(
            'sentence_length',
            $this->sentenceStatus($sentences),
            'Длина предложений',
            $this->longSentenceLabel($sentences),
            'Держите предложения короче ' . self::LONG_SENTENCE_WORDS . ' слов: без «паровозов».'
        );

        $checks[] = $this->check(
            'passive_voice',
            $this->passiveStatus($sentences),
            'Пассивный залог',
            $this->passiveLabel($sentences),
            'Пассив — меньше 10 %: «книга выдаётся» лучше заменить на «мы выдаём».'
        );

        $checks[] = $this->check(
            'transition_words',
            $this->transitionStatus($sentences),
            'Переходные слова',
            $this->transitionLabel($sentences),
            'Связки (однако, поэтому, например) — не меньше 30 % предложений.'
        );

        $checks[] = $this->check(
            'internal_links',
            $links['internal'] > 0 ? self::GOOD : self::BAD,
            'Внутренние ссылки',
            $links['internal'] . ' шт.',
            'Поставьте ссылку на услугу, раздел или другую запись сайта.'
        );

        $checks[] = $this->check(
            'external_links',
            $links['external'] > 0 ? self::GOOD : self::OK,
            'Внешние ссылки',
            $links['external'] . ' шт.',
            'Если тема требует подтверждений — сошлитесь на авторитетный источник.'
        );

        $checks[] = $this->check(
            'image_alt',
            $images['missing'] === 0 ? self::GOOD : self::BAD,
            'Alt у картинок',
            $images['total'] === 0 ? 'картинок нет' : 'без alt: ' . $images['missing'],
            'Опишите картинку словами: alt читают поисковик и скринридер.'
        );

        $checks[] = $this->check(
            'paragraph_length',
            $this->paragraphStatus($content),
            'Длина абзацев',
            $this->paragraphLabel($content),
            'Один абзац — одна мысль, до ' . self::LONG_PARAGRAPH_WORDS . ' слов.'
        );

        // Текст не заполнен (новый черновик или страница из блоков):
        // проверки содержимого не приговариваются, а честно помечаются как «нет данных».
        // Если в тексте есть разметка (картинки, ссылки, подзаголовки), проверки работают.
        $hasMarkup = $headings
            || ($links['internal'] + $links['external']) > 0
            || $images['total'] > 0;

        if ($words === 0 && !$hasMarkup) {
            $contentChecks = [
                'keyphrase_in_intro', 'keyphrase_density', 'headings_h1', 'headings_levels',
                'headings_frequency', 'sentence_length', 'passive_voice', 'transition_words',
                'internal_links', 'external_links', 'image_alt', 'paragraph_length',
            ];

            foreach ($checks as &$item) {
                if (in_array($item['id'], $contentChecks, true)) {
                    $item['status'] = self::OK;
                    $item['value'] = 'текста нет';
                }
            }
            unset($item);
        }

        $bad = 0;
        $warnings = 0;
        foreach ($checks as $item) {
            if ($item['status'] === self::BAD) {
                $bad++;
            }
            elseif ($item['status'] === self::OK) {
                $warnings++;
            }
        }

        if ($bad === 0 && $warnings <= 2) {
            $status = self::GOOD;
        }
        elseif ($bad <= 2) {
            $status = self::OK;
        }
        else {
            $status = self::BAD;
        }

        return [
            'status' => $status,
            'label' => $this->statusLabel($status),
            'checks' => $checks,
            'stats' => [
                'words' => $words,
                'sentences' => count($sentences),
                'headings' => count($headings),
                'minutes' => max(1, (int) ceil($words / 180)),
                'density' => round($density, 2),
                'bad' => $bad,
                'warnings' => $warnings,
            ],
        ];
    }

    /**
     * statusLabel — человеческая подпись светофора.
     */
    public function statusLabel(string $status): string
    {
        if ($status === self::GOOD) {
            return 'Хорошо';
        }

        if ($status === self::OK) {
            return 'Можно лучше';
        }

        return 'Нужно доработать';
    }

    /**
     * check собирает одну строку анализа.
     *
     * @return array<string, string>
     */
    protected function check(string $id, string $status, string $title, string $value, string $hint): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'title' => $title,
            'value' => $value,
            'hint' => $hint,
        ];
    }

    /**
     * plainText превращает HTML редактора в текст для подсчётов.
     */
    protected function plainText(string $html): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = preg_replace('/<\/(p|div|li|tr|h[1-6]|blockquote|section|article)>/i', "\n\n", (string) $text);
        $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n[ \t]+/u', "\n", (string) $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", (string) $text);

        return trim((string) $text);
    }

    /**
     * wordCount — число слов в тексте.
     */
    protected function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * sentences — предложения текста.
     *
     * @return array<int, string>
     */
    protected function sentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, function ($part) {
            return trim((string) $part) !== '';
        }));
    }

    /**
     * headings — заголовки H1–H6 в порядке появления.
     *
     * @return array<int, array{level: int, text: string}>
     */
    protected function headings(string $html): array
    {
        if (!preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $headings = [];
        foreach ($matches as $match) {
            $headings[] = [
                'level' => (int) $match[1],
                'text' => $this->plainText($match[2]),
            ];
        }

        return $headings;
    }

    /**
     * countHeadings — сколько заголовков заданного уровня.
     *
     * @param  array<int, array{level: int, text: string}>  $headings
     */
    protected function countHeadings(array $headings, int $level): int
    {
        return count(array_filter($headings, function ($heading) use ($level) {
            return $heading['level'] === $level;
        }));
    }

    /**
     * firstParagraph — первый абзац текста.
     */
    protected function firstParagraph(string $html): string
    {
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $match)) {
            return $this->plainText($match[1]);
        }

        $parts = preg_split('/\n{2,}/u', $this->plainText($html)) ?: [];

        return trim((string) ($parts[0] ?? ''));
    }

    /**
     * links — внутренние и внешние ссылки.
     *
     * @return array{internal: int, external: int}
     */
    protected function links(string $html): array
    {
        $internal = 0;
        $external = 0;

        if (preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $href = trim(html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($href === '' || str_starts_with($href, '#')) {
                    continue;
                }

                if (preg_match('/^(mailto:|tel:|javascript:)/i', $href)) {
                    continue;
                }

                if (preg_match('#^(https?:)?//#i', $href)) {
                    if (preg_match('#^https?://#i', $href)) {
                        $external++;
                    }
                    continue;
                }

                $internal++;
            }
        }

        return ['internal' => $internal, 'external' => $external];
    }

    /**
     * images — сколько картинок и у скольких нет alt.
     *
     * @return array{total: int, missing: int}
     */
    protected function images(string $html): array
    {
        $total = preg_match_all('/<img\b[^>]*>/i', $html) ?: 0;
        $missing = 0;

        if ($total > 0 && preg_match_all('/<img\b[^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                $alt = '';
                if (preg_match('/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $altMatch)) {
                    $alt = trim(html_entity_decode($altMatch[2] !== '' ? $altMatch[2] : ($altMatch[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                if ($alt === '') {
                    $missing++;
                }
            }
        }

        return ['total' => (int) $total, 'missing' => $missing];
    }

    /**
     * density — плотность ключевой фразы в процентах.
     */
    protected function density(string $plain, string $keyphrase): float
    {
        $words = $this->wordCount($plain);
        $pattern = $this->phrasePattern($keyphrase);

        if ($words === 0 || $pattern === '') {
            return 0.0;
        }

        $occurrences = preg_match_all('/' . $pattern . '/u', $this->normalize($plain)) ?: 0;
        $phraseWords = $this->wordCount($keyphrase);

        return ($occurrences * $phraseWords / $words) * 100;
    }

    /**
     * containsKeyphrase — входит ли фраза в текст с учётом регистра и ё.
     */
    protected function containsKeyphrase(string $haystack, string $keyphrase): bool
    {
        $pattern = $this->phrasePattern($keyphrase);
        if ($pattern === '') {
            return false;
        }

        return (bool) preg_match('/' . $pattern . '/u', $this->normalize($haystack));
    }

    /**
     * slugContainsKeyphrase — есть ли в адресе транслит всех слов фразы.
     */
    protected function slugContainsKeyphrase(string $slug, string $keyphrase): bool
    {
        $slug = mb_strtolower(trim($slug));
        $words = preg_split('/\s+/u', $this->normalize($keyphrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($slug === '' || !$words) {
            return false;
        }

        foreach ($words as $word) {
            $pattern = $this->translitPattern($word);
            if ($pattern === '') {
                continue;
            }

            if (!preg_match('/(?:^|-)' . $pattern . '(?:-|$)/u', $slug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * phrasePattern — регулярное выражение фразы (слова через пробел или дефис).
     */
    protected function phrasePattern(string $keyphrase): string
    {
        $words = preg_split('/\s+/u', $this->normalize($keyphrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (!$words) {
            return '';
        }

        return implode('[\s\-]+', array_map(function ($word) {
            return preg_quote($word, '/');
        }, $words));
    }

    /**
     * translitPattern — шаблон латиницы для русского слова.
     *
     * Каждая буква даёт класс возможных вариантов транслита: адрес мог
     * собираться разными инструментами (ж → zh или j, х → h или kh).
     */
    protected function translitPattern(string $word): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => '(?:e|yo)', 'ж' => '(?:zh|j)', 'з' => 'z', 'и' => 'i',
            'й' => '(?:y|i|j)', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => '(?:h|kh)', 'ц' => '(?:c|ts)', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => '(?:sch|shch)', 'ъ' => '', 'ы' => '(?:y|i)',
            'ь' => '', 'э' => 'e', 'ю' => '(?:yu|iu|u)', 'я' => '(?:ya|ia)',
        ];

        $pattern = '';
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $letter) {
            if (isset($map[$letter])) {
                $pattern .= $map[$letter];
                continue;
            }

            if (preg_match('/^[a-z0-9]$/', $letter)) {
                $pattern .= $letter;
                continue;
            }

            return '';
        }

        return $pattern;
    }

    /**
     * normalize — нижний регистр и ё → е.
     */
    protected function normalize(string $text): string
    {
        return mb_strtolower(str_replace(['ё', 'Ё'], ['е', 'е'], $text));
    }

    /**
     * densityStatus — светофор плотности.
     */
    protected function densityStatus(float $density, string $keyphrase): string
    {
        if ($keyphrase === '') {
            return self::OK;
        }

        if ($density <= 0) {
            return self::BAD;
        }

        if ($density >= self::DENSITY_MIN && $density <= self::DENSITY_MAX) {
            return self::GOOD;
        }

        if ($density >= self::DENSITY_MIN / 2 && $density <= self::DENSITY_MAX * 2) {
            return self::OK;
        }

        return self::BAD;
    }

    /**
     * rangeStatus — светофор для длины: в норме, рядом, далеко.
     */
    protected function rangeStatus(int $length, int $min, int $max, int $soft, int $tolerance): string
    {
        if ($length === 0) {
            return self::BAD;
        }

        if ($length >= $min && $length <= $max) {
            return self::GOOD;
        }

        if ($length >= max(1, $min - $soft) && $length <= $max + $tolerance) {
            return self::OK;
        }

        return self::BAD;
    }

    /**
     * levelsStatus — светофор порядка заголовков.
     *
     * @param  array<int, array{level: int, text: string}>  $headings
     */
    protected function levelsStatus(array $headings): string
    {
        $previous = 1;

        foreach ($headings as $heading) {
            if ($heading['level'] > $previous + 1) {
                return self::BAD;
            }

            $previous = $heading['level'];
        }

        return self::GOOD;
    }

    /**
     * levelsLabel — какие уровни реально использованы.
     *
     * @param  array<int, array{level: int, text: string}>  $headings
     */
    protected function levelsLabel(array $headings): string
    {
        if (!$headings) {
            return 'подзаголовков нет';
        }

        $levels = array_values(array_unique(array_map(function ($heading) {
            return $heading['level'];
        }, $headings)));
        sort($levels);

        return 'H' . implode(', H', $levels);
    }

    /**
     * subheadingStatus — светофор частоты подзаголовков.
     *
     * @param  array<int, array{level: int, text: string}>  $headings
     */
    protected function subheadingStatus(int $words, array $headings): string
    {
        if ($words === 0) {
            return self::OK;
        }

        if (!$headings) {
            return $words > self::SUBHEADING_WORDS ? self::BAD : self::OK;
        }

        $perHeading = $words / count($headings);

        if ($perHeading <= self::SUBHEADING_WORDS) {
            return self::GOOD;
        }

        return $perHeading <= self::SUBHEADING_WORDS * 1.5 ? self::OK : self::BAD;
    }

    /**
     * sentenceStatus — светофор длинных предложений.
     *
     * @param  array<int, string>  $sentences
     */
    protected function sentenceStatus(array $sentences): string
    {
        $total = count($sentences);
        if ($total === 0) {
            return self::OK;
        }

        $long = 0;
        foreach ($sentences as $sentence) {
            if ($this->wordCount($sentence) > self::LONG_SENTENCE_WORDS) {
                $long++;
            }
        }

        $percent = $long / $total * 100;

        if ($percent <= 25) {
            return self::GOOD;
        }

        return $percent <= 40 ? self::OK : self::BAD;
    }

    /**
     * longSentenceLabel — сколько предложений длиннее нормы.
     *
     * @param  array<int, string>  $sentences
     */
    protected function longSentenceLabel(array $sentences): string
    {
        $long = 0;
        foreach ($sentences as $sentence) {
            if ($this->wordCount($sentence) > self::LONG_SENTENCE_WORDS) {
                $long++;
            }
        }

        return $long . ' из ' . count($sentences);
    }

    /**
     * passiveStatus — светофор пассива.
     *
     * @param  array<int, string>  $sentences
     */
    protected function passiveStatus(array $sentences): string
    {
        $total = count($sentences);
        if ($total === 0) {
            return self::OK;
        }

        $percent = $this->passiveCount($sentences) / $total * 100;

        if ($percent < 10) {
            return self::GOOD;
        }

        return $percent < 20 ? self::OK : self::BAD;
    }

    /**
     * passiveLabel — подпись проверки пассива.
     *
     * @param  array<int, string>  $sentences
     */
    protected function passiveLabel(array $sentences): string
    {
        $total = count($sentences);
        if ($total === 0) {
            return '—';
        }

        return $this->passiveCount($sentences) . ' из ' . $total;
    }

    /**
     * passiveCount — сколько предложений содержит маркер пассива.
     *
     * @param  array<int, string>  $sentences
     */
    protected function passiveCount(array $sentences): int
    {
        $count = 0;
        foreach ($sentences as $sentence) {
            $normalized = $this->normalize($sentence);
            foreach ($this->passiveMarkers as $marker) {
                if (preg_match('/\b' . preg_quote($marker, '/') . '\b/u', $normalized)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * transitionStatus — светофор переходных слов.
     *
     * @param  array<int, string>  $sentences
     */
    protected function transitionStatus(array $sentences): string
    {
        $total = count($sentences);
        if ($total === 0) {
            return self::OK;
        }

        $percent = $this->transitionCount($sentences) / $total * 100;

        if ($percent >= 30) {
            return self::GOOD;
        }

        return $percent >= 20 ? self::OK : self::BAD;
    }

    /**
     * transitionLabel — подпись проверки переходных слов.
     *
     * @param  array<int, string>  $sentences
     */
    protected function transitionLabel(array $sentences): string
    {
        $total = count($sentences);
        if ($total === 0) {
            return '—';
        }

        return $this->transitionCount($sentences) . ' из ' . $total;
    }

    /**
     * transitionCount — сколько предложений начинается со связки.
     *
     * @param  array<int, string>  $sentences
     */
    protected function transitionCount(array $sentences): int
    {
        $count = 0;
        foreach ($sentences as $sentence) {
            $normalized = ltrim($this->normalize($sentence));
            foreach ($this->transitionWords as $word) {
                if (str_starts_with($normalized, $word)) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * paragraphStatus — светофор длины абзацев.
     */
    protected function paragraphStatus(string $html): string
    {
        $longest = $this->longestParagraph($html);

        if ($longest === 0) {
            return self::OK;
        }

        if ($longest <= self::LONG_PARAGRAPH_WORDS) {
            return self::GOOD;
        }

        return $longest <= self::LONG_PARAGRAPH_WORDS * 2 ? self::OK : self::BAD;
    }

    /**
     * paragraphLabel — подпись проверки абзацев.
     */
    protected function paragraphLabel(string $html): string
    {
        $longest = $this->longestParagraph($html);

        return $longest === 0 ? '—' : 'самый длинный: ' . $longest . ' слов';
    }

    /**
     * longestParagraph — сколько слов в самом длинном абзаце.
     */
    protected function longestParagraph(string $html): int
    {
        if (!preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            return $this->wordCount($this->plainText($html));
        }

        $longest = 0;
        foreach ($matches[1] as $paragraph) {
            $longest = max($longest, $this->wordCount($this->plainText($paragraph)));
        }

        return $longest;
    }

    /**
     * formatNumber — число без хвоста .0.
     */
    protected function formatNumber(float $value): string
    {
        $rounded = round($value, 2);
        if ($rounded == (int) $rounded) {
            return (string) (int) $rounded;
        }

        return rtrim(rtrim(number_format($rounded, 2, ',', ''), '0'), ',');
    }
}
