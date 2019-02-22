<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

//include configuration
include __DIR__ .'/config.php';

//include routes
include __DIR__ .'/routing.php';

//
$app['user'] = $app->share(function () use ($app){
     //get user from db
    $sql = "SELECT iduser, email, name, roles, status, gender, dni, address, phone, movil FROM users WHERE email = :email";;
	$u = $app['dbs']['local']->executeQuery($sql, array("email" => $user = $app['security.token_storage']->getToken()->getUser()))->fetch(PDO::FETCH_ASSOC);
	$u['roles'] = explode(',',$u['roles']);
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $u['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_ASSOC);
	if($vcf){
	    $u['idvcf'] = $vcf['idvcf'];
	    $u['has_vcf'] = true;
	}else{
	    $u['idvcf'] = -1;
	    $u['has_vcf'] = false;
	}
	return $u;
});

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Nass600\Silex\Provider\SnappyServiceProvider(), array(
    'snappy.pdf.binary' => __DIR__ . '/../vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64',
    'snappy.pdf.options' => array(
        'footer-center' => 'page [page]'
    ),
    /*
    'snappy.image.binary' => '/path/to/wkhtmltoimage',
    'snappy.image.options' => array(
        'format' => 'png'
    )*/
));

$app->run();