<?php namespace Elv\Library;

use Elv\Library\Classes\Brand;
use Elv\Library\Classes\Content;
use Elv\Library\Classes\Http\SecurityHeaders;
use Elv\Library\Classes\Labels;
use Elv\Library\Classes\Media;
use Elv\Library\Classes\Seo;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'ELV Library',
            'description' => 'Контент сайта, заявки, слоты услуг и поиск.',
            'author' => 'ELV',
            'icon' => 'icon-book'
        ];
    }

    public function register()
    {
        $this->discoverConsoleCommands();
    }

    public function registerSchedule($schedule)
    {
        $schedule->command('elv:health-report')->hourly()->withoutOverlapping();
        $schedule->command('elv:slots-generate')->dailyAt('03:10')->withoutOverlapping();
    }

    public function registerPermissions()
    {
        return [
            'elv.library.requests' => [
                'label' => 'Обращения с сайта',
                'tab' => 'Библиотека',
            ],
            'elv.library.bookings' => [
                'label' => 'Брони услуг',
                'tab' => 'Библиотека',
            ],
            'elv.library.orders' => [
                'label' => 'Заказы',
                'tab' => 'Библиотека',
            ],
            'elv.library.subscribers' => [
                'label' => 'Подписчики рассылки',
                'tab' => 'Библиотека',
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'library' => [
                'label' => 'Контент',
                'url' => \Backend::url('elv/library/requests'),
                'icon' => 'icon-book',
                'order' => 400,
                'permissions' => ['elv.library.requests'],
                'sideMenu' => [
                    'requests' => [
                        'label' => 'Обращения',
                        'icon' => 'icon-envelope',
                        'url' => \Backend::url('elv/library/requests'),
                        'permissions' => ['elv.library.requests'],
                    ],
                    'bookings' => [
                        'label' => 'Брони',
                        'icon' => 'icon-calendar',
                        'url' => \Backend::url('elv/library/bookings'),
                        'permissions' => ['elv.library.bookings'],
                    ],
                    'orders' => [
                        'label' => 'Заказы',
                        'icon' => 'icon-shopping-cart',
                        'url' => \Backend::url('elv/library/orders'),
                        'permissions' => ['elv.library.orders'],
                    ],
                    'subscribers' => [
                        'label' => 'Подписчики',
                        'icon' => 'icon-users',
                        'url' => \Backend::url('elv/library/subscribers'),
                        'permissions' => ['elv.library.subscribers'],
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
            ->pushMiddleware(\Elv\Library\Classes\Http\CompressResponse::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(\Elv\Library\Classes\Http\LegacyRedirect::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(SecurityHeaders::class);
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'elv_picture' => function ($src, $alt = '', $options = []) {
                    return Media::picture($src, (string) $alt, (array) $options);
                },
                'elv_css' => function ($relative) {
                    return Media::inlineCss((string) $relative);
                },
                'elv_lines' => function ($value) {
                    return Media::lines($value);
                },
                'elv_records' => function ($handle, $limit = null) {
                    return Content::records((string) $handle, $limit);
                },
                'elv_records_in_section' => function ($handle, $sectionHandle, $code, $limit = null) {
                    return Content::recordsInSection((string) $handle, (string) $sectionHandle, (string) $code, $limit);
                },
                'elv_record' => function ($handle, $slug) {
                    return Content::record((string) $handle, $slug);
                },
                'elv_sections' => function ($handle) {
                    return Content::sections((string) $handle);
                },
                'elv_section_code' => function ($handle, $id) {
                    return Content::sectionCode((string) $handle, $id);
                },
                'elv_section_title' => function ($handle, $id) {
                    return Content::sectionTitle((string) $handle, $id);
                },
                'elv_tag' => function ($code) {
                    return Labels::tag($code);
                },
                'elv_event_category' => function ($code) {
                    return Labels::eventCategory($code);
                },
                'elv_event_gradient' => function ($code) {
                    return Labels::eventGradient($code);
                },
                'elv_event_categories' => function () {
                    return Labels::eventCategories();
                },
                'elv_format' => function ($code) {
                    return Labels::format($code);
                },
                'elv_availability' => function ($code) {
                    return Labels::availability($code);
                },
                'elv_age' => function ($code) {
                    return Labels::age($code);
                },
                'elv_cover_color' => function ($title) {
                    return Media::coverColor($title);
                },
                'elv_collection_color' => function ($color) {
                    return Media::collectionColor($color);
                },
                'elv_price_label' => function ($label, $from = 0) {
                    return Media::priceLabel($label, $from);
                },
                'elv_schedule' => function ($value) {
                    return Media::schedule($value);
                },
                'elv_phone_link' => function ($value) {
                    return Media::phoneLink($value);
                },
                'elv_month_short' => function ($value) {
                    return Media::dateRu($value, 'M');
                },
                'elv_day' => function ($value) {
                    return Media::dateRu($value, 'd');
                },
                'elv_time' => function ($value) {
                    return Media::dateRu($value, 'H:i');
                },
                'elv_slot_map' => function ($serviceCode, $days = 60) {
                    return \Elv\Library\Models\Slot::jsonOccupiedMap((string) $serviceCode, (int) $days);
                },
                'elv_canonical' => function () {
                    return Seo::canonical();
                },
                'elv_csp_nonce' => function () {
                    return SecurityHeaders::nonce();
                },
                'elv_csrf' => function () {
                    return csrf_field();
                },
                'elv_jsonld' => function ($page = null) {
                    return Seo::jsonld($page);
                },
                'elv_brand' => function ($key = 'name') {
                    return Brand::get((string) $key);
                },
                'elv_brand_text' => function ($text) {
                    return Brand::replace((string) $text);
                },
            ],
            'filters' => [
                'elv_image' => function ($value) {
                    return Media::imagePath($value);
                },
                'elv_price' => function ($value) {
                    return Media::price($value);
                },
                'elv_money' => function ($value) {
                    return Media::money($value);
                },
                'elv_num' => function ($value) {
                    return Media::num($value);
                },
                'elv_date' => function ($value, $format = 'j F Y') {
                    return Media::dateRu($value, (string) $format);
                },
                'elv_text' => function ($value) {
                    return Media::value($value);
                },
            ],
        ];
    }
}
