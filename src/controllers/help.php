<?php

use Symfony\Component\HttpFoundation\Request;


$controller = $app['controllers_factory'];

$controller->get('/modals/{id}', function($id) use($app) {
   	return $app['twig']->render('/help/'.$id);
});


$app->mount('/help', $controller);