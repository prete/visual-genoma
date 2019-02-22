<?php

//database config
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'dbs.options' => array (
        //mysql local
        'local' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'IP',
            'port'      => 3306,
            'dbname'    => 'bio',
            'user'      => 'user',
            'password'  => 'password'
        ),
        //https://genome.ucsc.edu/cgi-bin/hgTables
        'ucsc.edu' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'genome-mysql.cse.ucsc.edu',
            'dbname'    => 'hg38',
            'user'      => 'genome',
            'password'  => ''
        ),
    ),
));