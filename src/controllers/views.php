<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

$controller->get('/', function() use($app) {
	//no anda por estar afuera del firewall... habria que ver por cookie
	if($app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
        return $app->redirect('/dashboard');
    }
	return $app['twig']->render('landing.html.twig');
});

$controller->get('/index', function() use($app) {
	return $app['twig']->render('index.html.twig');
});

$controller->get('/dashboard', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }

	return $app['twig']->render('dashboard.html.twig');
});

$controller->get('/clinical', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
   	return $app['twig']->render('clinical.html.twig');
});

$controller->get('/ancestry/intro', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
   	return $app['twig']->render('/ancestry/intro.html.twig');
});

$controller->get('/ancestry/worldmap', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
   	return $app['twig']->render('/ancestry/worldmap.html.twig');
});

$controller->get('/genome_browser', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
	$sql = "SELECT	*
			 FROM	chrom_sizes
			 ORDER	BY CAST(chromosome AS UNSIGNED INT)";
    $chromosomes = $app['dbs']['local']->executeQuery($sql)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('browser.html.twig', array(
   		'chromosomes' => $chromosomes
   	));
});

$controller->get('/anatogram/intro', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
   	return $app['twig']->render('/anatogram/intro.html.twig');
});

$controller->get('/anatogram/body', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser, email, gender FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
	$result = new stdClass;
	$result->email = $user['email'];
	if($user['gender'] == "Femenino") {
		$result->isfemale = true;
	}
	
    return $app['twig']->render('/anatogram/body.html.twig', (array) $result);
});

$controller->get('/circos', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
    
   	return $app['twig']->render('circos.html.twig');
});

$controller->get('/reports', function() use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$params = array("iduser" => $user['iduser']);
	$vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }

	return $app['twig']->render('reports.html.twig');
});

$controller->get('/about', function() use($app) {
   	return $app['twig']->render('about.html.twig');
});

$controller->get('/faq', function() use($app) {
   	return $app['twig']->render('faq.html.twig');
});

$controller->get('/intro', function() use($app) {
   	return $app['twig']->render('intro.html.twig');
});

$controller->get('/novcf', function() use($app) {
   	return $app['twig']->render('novcf.html.twig');
});

$app->mount('/', $controller);