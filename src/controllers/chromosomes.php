<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

$controller->get('/{id}', function($id) use($app) {
	if( in_array( $id, $app['bio']->chromosomes ) ){
		return $app['twig']->render('/chromosome.html.twig', array('chrom'=>$id));	
	}
	return $app->redirect('/dashboard');
});

$controller->get('/{id}/{pos}', function($id, $pos) use($app) {
	if( in_array( $id, $app['bio']->chromosomes ) ){
		return $app['twig']->render('/chromosome.html.twig', array('chrom'=>$id, 'position'=> $pos));	
	}
	return $app->redirect('/dashboard');
});

$app->mount('/chromosomes', $controller);