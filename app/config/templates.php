<?php

$app->register(new Silex\Provider\HttpFragmentServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

//template config
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../../src/views',
    'twig.class_path' => __DIR__.'/../../vendor/Twig/lib',
    'twig.options' => array('cache' => __DIR__.'/../cache'),
));