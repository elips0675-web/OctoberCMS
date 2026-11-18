<?php

return [
    'items' => [
        [
            'xml_id' => 'elv-news-001',
            'sort' => 100,
            'name' => 'Цифровой читальный зал',
            'code' => 'cifrovoy-chitalnyy-zal',
            'active_from' => '2026-11-02 10:00:00',
            'section' => 'library',
            'image' => null,
            'preview_text' => 'Анонс первой новости.',
            'detail_text' => '<p>Текст первой новости.</p>',
            'properties' => [
                'AUTHOR' => 'Пресс-служба',
                'TAGS' => ['digital', 'library'],
                'READ_TIME' => '4 мин',
            ],
        ],
        [
            'xml_id' => 'elv-news-002',
            'sort' => 200,
            'name' => 'Новые поступления',
            'code' => 'novye-postupleniya',
            'active_from' => '2026-10-30 09:30:00',
            'section' => 'announcements',
            'image' => null,
            'preview_text' => 'Анонс второй новости.',
            'detail_text' => '<p>Текст второй новости.</p>',
            'properties' => [
                'AUTHOR' => 'Отдел комплектования',
                'TAGS' => ['books'],
                'READ_TIME' => '3 мин',
            ],
        ],
    ],
];
