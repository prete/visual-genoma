<?php

//log config
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../logs/'.date("Y-m-d").'.log',
));
