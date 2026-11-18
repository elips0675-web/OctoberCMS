<?php namespace Elv\Site\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Redirects — редиректы, которые редактор заводит сам.
 *
 * Хардкод-карта старых адресов 1С-Битрикс (`Classes\Http\LegacyRedirect`)
 * остаётся в коде: её правит разработчик при переезде. Эта страница — для
 * текущей жизни сайта: переименованная страница, склеенный раздел, ссылка из
 * старого письма.
 */
class Redirects extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['elv.site.redirects'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Elv.Site', 'site', 'redirects');
    }
}
