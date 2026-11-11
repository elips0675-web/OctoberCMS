<?php namespace Nlb\Library\Controllers;

use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Requests — обращения с формы обратной связи.
 */
class Requests extends Controller
{
    public $implement = [
        ListController::class,
        FormController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['nlb.library.requests'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Nlb.Library', 'library', 'requests');
    }
}
