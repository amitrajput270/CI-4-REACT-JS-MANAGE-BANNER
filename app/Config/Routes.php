<?php

use App\Controllers\BannerController;

$routes->get('/', 'Home::index');

$routes->group('api', function ($routes) {
    $routes->options('(:any)', function () {
        return service('response')->setStatusCode(200);
    });
    $routes->get('banner', [BannerController::class, 'index']);
    $routes->get('banner/(:num)', [BannerController::class, 'show']);
    $routes->post('banner', [BannerController::class, 'create']);
    $routes->put('banner/(:num)', [BannerController::class, 'update']);
    $routes->delete('banner/(:num)', [BannerController::class, 'delete']);
});
