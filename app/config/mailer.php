<?php

use Silex\Provider\SwiftmailerServiceProvider;

$app->register(new Silex\Provider\SwiftmailerServiceProvider(), array(
    'swiftmailer.options' => array(
        'host' => 'mail.smtp2go.com',
        'port' => 2525 ,
        'username' => 'visualgenoma',
        'password' => 'password',
        'encryption' =>  null,
        'auth_mode' => 'login'
    )
));

$app['swiftmailer.use_spool'] = false;