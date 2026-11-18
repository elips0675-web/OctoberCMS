<?php namespace Elv\Site;

use Elv\Site\Classes\Brand;
use Elv\Site\Classes\Content;
use Elv\Site\Classes\Http\SecurityHeaders;
use Elv\Site\Classes\Labels;
use Elv\Site\Classes\Media;
use Elv\Site\Classes\Seo;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'ELV Site',
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
            'elv.site.requests' => [
                'label' => 'Обращения с сайта',
                'tab' => 'Сайт',
            ],
            'elv.site.bookings' => [
                'label' => 'Брони услуг',
                'tab' => 'Сайт',
            ],
            'elv.site.orders' => [
                'label' => 'Заказы',
                'tab' => 'Сайт',
            ],
            'elv.site.subscribers' => [
                'label' => 'Подписчики рассылки',
                'tab' => 'Сайт',
            ],
            'elv.site.redirects' => [
                'label' => 'Редиректы',
                'tab' => 'Сайт',
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'site' => [
                'label' => 'Контент',
                'url' => \Backend::url('elv/site/requests'),
                'icon' => 'icon-book',
                'order' => 400,
                'permissions' => ['elv.site.requests'],
                'sideMenu' => [
                    'requests' => [
                        'label' => 'Обращения',
                        'icon' => 'icon-envelope',
                        'url' => \Backend::url('elv/site/requests'),
                        'permissions' => ['elv.site.requests'],
                    ],
                    'bookings' => [
                        'label' => 'Брони',
                        'icon' => 'icon-calendar',
                        'url' => \Backend::url('elv/site/bookings'),
                        'permissions' => ['elv.site.bookings'],
                    ],
                    'orders' => [
                        'label' => 'Заказы',
                        'icon' => 'icon-shopping-cart',
                        'url' => \Backend::url('elv/site/orders'),
                        'permissions' => ['elv.site.orders'],
                    ],
                    'subscribers' => [
                        'label' => 'Подписчики',
                        'icon' => 'icon-users',
                        'url' => \Backend::url('elv/site/subscribers'),
                        'permissions' => ['elv.site.subscribers'],
                    ],
                    'redirects' => [
                        'label' => 'Редиректы',
                        'icon' => 'icon-random',
                        'url' => \Backend::url('elv/site/redirects'),
                        'permissions' => ['elv.site.redirects'],
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
            ->pushMiddleware(\Elv\Site\Classes\Http\CompressResponse::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(\Elv\Site\Classes\Http\LegacyRedirect::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(SecurityHeaders::class);

        $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(\Elv\Site\Classes\Http\ManagedRedirect::class);
    }

    public function registerContentFields()
    {
        return [
            \Elv\Site\Classes\ContentFields\SeoPanelField::class => 'seo_panel',
        ];
    }

    public function registerFormWidgets()
    {
        return [
            \Elv\Site\FormWidgets\SeoPanel::class => 'elvseopanel',
        ];
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
                },                'elv_records_in_section' => function ($handle, $sectionHandle, $code, $limit = null) {
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
                    return \Elv\Site\Models\Slot::jsonOccupiedMap((string) $serviceCode, (int) $days);
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
                'elv_seo_image' => function ($page = null) {
                    $image = is_object($page) ? trim((string) ($page->seo_image ?? '')) : '';

                    if ($image !== '') {
                        return preg_match('#^https?://#i', $image) ? $image : url($image);
                    }

                    $default = Seo::defaultImage();

                    return $default !== ''
                        ? $default
                        : url('themes/elv/assets/images/hero-1.jpg');
                },
                'elv_seo_verification' => function () {
                    return Seo::verification();
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
