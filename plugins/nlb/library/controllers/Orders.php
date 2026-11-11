<?php namespace Nlb\Library\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Orders — заказы сувениров из каталога.
 */
class Orders extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['nlb.library.orders'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Nlb.Library', 'library', 'orders');
    }
}
