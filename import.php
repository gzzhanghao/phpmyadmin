<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Core script for import, this is just the glue around all other stuff
 *
 * @package PhpMyAdmin
 */

namespace PMA;

require_once 'libraries/di/Container.class.php';
require_once 'libraries/controllers/ImportController.class.php';

$container = DI\Container::getDefaultContainer();
$container->factory('PMA\Controllers\ImportController');
$container->alias(
    'ImportController', 'PMA\Controllers\ImportController'
);

/* Define dependencies for the concerned controller */
$dependency_definitions = array();

/** @var Controllers\ImportController $controller */
$controller = $container->get('ImportController', $dependency_definitions);
$controller->indexAction();
