<?php namespace Nlb\Library;

use Nlb\Library\Classes\Content;
use Nlb\Library\Classes\Labels;
use Nlb\Library\Classes\Media;
use Nlb\Library\Classes\Seo;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'NLB Library',
            'description' => 'Контент библиотеки, заявки, слоты услуг и поиск.',
            'author' => 'NLB',
            'icon' => 'icon-book'
        ];
    }

    public function register()
    {
        $this->discoverConsoleCommands();
    }

    public function registerPermissions()
    {
        return [
            'nlb.library.requests' => [
                'label' => 'Обращения с сайта',
                'tab' => 'Библиотека',
            ],
            'nlb.library.bookings' => [
                'label' => 'Брони услуг',
                'tab' => 'Библиотека',
            ],
            'nlb.library.orders' => [
                'label' => 'Заказы сувениров',
                'tab' => 'Библиотека',
            ],
            'nlb.library.subscribers' => [
                'label' => 'Подписчики рассылки',
                'tab' => 'Библиотека',
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'library' => [
                'label' => 'Библиотека',
                'url' => \Backend::url('nlb/library/requests'),
                'icon' => 'icon-book',
                'order' => 400,
                'permissions' => ['nlb.library.requests'],
                'sideMenu' => [
                    'requests' => [
                        'label' => 'Обращения',
                        'icon' => 'icon-envelope',
                        'url' => \Backend::url('nlb/library/requests'),
                        'permissions' => ['nlb.library.requests'],
                    ],
                    'bookings' => [
                        'label' => 'Брони',
                        'icon' => 'icon-calendar',
                        'url' => \Backend::url('nlb/library/bookings'),
                        'permissions' => ['nlb.library.bookings'],
                    ],
                    'orders' => [
                        'label' => 'Заказы',
                        'icon' => 'icon-shopping-cart',
                        'url' => \Backend::url('nlb/library/orders'),
                        'permissions' => ['nlb.library.orders'],
                    ],
                    'subscribers' => [
                        'label' => 'Подписчики',
                        'icon' => 'icon-users',
                        'url' => \Backend::url('nlb/library/subscribers'),
                        'permissions' => ['nlb.library.subscribers'],
                    ],
                ],
            ],
        ];
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(\Nlb\Library\Classes\Http\CompressResponse::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(\Nlb\Library\Classes\Http\LegacyRedirect::class);
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'nlb_picture' => function ($src, $alt = '', $options = []) {
                    return Media::picture($src, (string) $alt, (array) $options);
                },
                'nlb_css' => function ($relative) {
                    return Media::inlineCss((string) $relative);
                },
                'nlb_lines' => function ($value) {
                    return Media::lines($value);
                },
                'nlb_records' => function ($handle, $limit = null) {
                    return Content::records((string) $handle, $limit);
                },
                'nlb_records_in_section' => function ($handle, $sectionHandle, $code, $limit = null) {
                    return Content::recordsInSection((string) $handle, (string) $sectionHandle, (string) $code, $limit);
                },
                'nlb_record' => function ($handle, $slug) {
                    return Content::record((string) $handle, $slug);
                },
                'nlb_sections' => function ($handle) {
                    return Content::sections((string) $handle);
                },
                'nlb_section_code' => function ($handle, $id) {
                    return Content::sectionCode((string) $handle, $id);
                },
                'nlb_section_title' => function ($handle, $id) {
                    return Content::sectionTitle((string) $handle, $id);
                },
                'nlb_tag' => function ($code) {
                    return Labels::tag($code);
                },
                'nlb_event_category' => function ($code) {
                    return Labels::eventCategory($code);
                },
                'nlb_event_gradient' => function ($code) {
                    return Labels::eventGradient($code);
                },
                'nlb_event_categories' => function () {
                    return Labels::eventCategories();
                },
                'nlb_format' => function ($code) {
                    return Labels::format($code);
                },
                'nlb_availability' => function ($code) {
                    return Labels::availability($code);
                },
                'nlb_age' => function ($code) {
                    return Labels::age($code);
                },
                'nlb_cover_color' => function ($title) {
                    return Media::coverColor($title);
                },
                'nlb_collection_color' => function ($color) {
                    return Media::collectionColor($color);
                },
                'nlb_price_label' => function ($label, $from = 0) {
                    return Media::priceLabel($label, $from);
                },
                'nlb_schedule' => function ($value) {
                    return Media::schedule($value);
                },
                'nlb_phone_link' => function ($value) {
                    return Media::phoneLink($value);
                },
                'nlb_month_short' => function ($value) {
                    return Media::dateRu($value, 'M');
                },
                'nlb_day' => function ($value) {
                    return Media::dateRu($value, 'd');
                },
                'nlb_time' => function ($value) {
                    return Media::dateRu($value, 'H:i');
                },
                'nlb_slot_map' => function ($serviceCode, $days = 60) {
                    return \Nlb\Library\Models\Slot::jsonOccupiedMap((string) $serviceCode, (int) $days);
                },
                'nlb_canonical' => function () {
                    $path = '/' . ltrim((string) request()->path(), '/');
                    if ($path !== '/') {
                        $path .= '/';
                    }

                    return url($path);
                },
                'nlb_csrf' => function () {
                    return csrf_field();
                },
                'nlb_jsonld' => function () {
                    return Seo::jsonld();
                },
            ],
            'filters' => [
                'nlb_image' => function ($value) {
                    return Media::imagePath($value);
                },
                'nlb_price' => function ($value) {
                    return Media::price($value);
                },
                'nlb_money' => function ($value) {
                    return Media::money($value);
                },
                'nlb_num' => function ($value) {
                    return Media::num($value);
                },
                'nlb_date' => function ($value, $format = 'j F Y') {
                    return Media::dateRu($value, (string) $format);
                },
                'nlb_text' => function ($value) {
                    return Media::value($value);
                },
            ],
        ];
    }
}
