<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['debug'] = true;

include __DIR__ .'/config/log.php';
include __DIR__ .'/config/db.php';
include __DIR__ .'/config/templates.php';
include __DIR__ .'/config/security.php';
include __DIR__ .'/config/mailer.php';

//zarazaza
$app['upload_folder'] = __DIR__.'/../uploads/';
$app['temp_folder'] = __DIR__.'/../temp';
$app['resources_folder'] = __DIR__.'/../resources';
$app['python_script'] = __DIR__.'/../processor/import_vcf.py';
 
//custom app configurations to be used in controllers
$app['bio'] = new stdClass;

$app['bio']->chromosomes = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,'X','Y'];

$app['bio']->chromosomeOrder = array(
     '1'=>1,   '2'=>2,   '3'=>3,   '4'=>4,   '5'=>5,   '6'=>6,   '7'=>7,  '8'=>8,    '9'=>9,  '10'=>10,
    '11'=>11, '12'=>12, '13'=>13, '14'=>14, '15'=>15, '16'=>16, '17'=>17, '18'=>18, '19'=>19, '20'=>20,
    '21'=>21, '22'=>22, 'X'=>23, 'Y'=>24);
    
$app['bio']->nucleotides = ['A','T','C','G'];

//translations
include __DIR__ .'/config/i18n.php';
