<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

$controller->get('tissues/minmax', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	// MIN/MAX por tejido
    $sql = "SELECT	MAX(adipose) AS `max_adipose`,
					MAX(adrenal) AS `max_adrenal`,
					MAX(brain) AS `max_brain`,
					MAX(breast) AS `max_breast`,
					MAX(colon) AS `max_colon`,
					MAX(heart) AS `max_heart`,
					MAX(kidney) AS `max_kidney`,
					MAX(leukocyte) AS `max_leukocyte`,
					MAX(liver) AS `max_liver`,
					MAX(lung) AS `max_lung`,
					MAX(lymph_node) AS `max_lymph_node`,
					MAX(ovary) AS `max_ovary`,
					MAX(prostate) AS `max_prostate`,
					MAX(skeletal_muscle) AS `max_skeletal_muscle`,
					MAX(testis) AS `max_testis`,
					MAX(thyroid) AS `max_thyroid`,
					MIN(adipose) AS `min_adipose`,
					MIN(adrenal) AS `min_adrenal`,
					MIN(brain) AS `min_brain`,
					MIN(breast) AS `min_breast`,
					MIN(colon) AS `min_colon`,
					MIN(heart) AS `min_heart`,
					MIN(kidney) AS `min_kidney`,
					MIN(leukocyte) AS `min_leukocyte`,
					MIN(liver) AS `min_liver`,
					MIN(lung) AS `min_lung`,
					MIN(lymph_node) AS `min_lymph_node`,
					MIN(ovary) AS `min_ovary`,
					MIN(prostate) AS `min_prostate`,
					MIN(skeletal_muscle) AS `min_skeletal_muscle`,
					MIN(testis) AS `min_testis`,
					MIN(thyroid) AS `min_thyroid`
			FROM	`gxa_tissues`";
    $result = $app['dbs']['local']->executeQuery($sql)->fetch(PDO::FETCH_OBJ);
    return $app->json($result);
});


$controller->get('tissues/genes/filter/variants', function(Request $request) use($app) {
	$result = new stdClass;
	
	if(!$app['user']['has_vcf']){
    	$app->abort(400, "VCF no encontrado.");
    }
	$params = array("idvcf" => $app['user']['idvcf']);
	
	// GENES VARIADOS
    $sql = "SELECT	g.*
			FROM	gxa_tissues AS g 
			WHERE	EXISTS (
						SELECT	1
						FROM	ref_genes AS r,
								variants AS v,
								functional_annotations AS f
						WHERE	v.idvariant = r.idvariant 
								AND r.gene = g.gene
								AND v.idvariant = f.idvariant
								AND f.Annotation LIKE '%missense_variant%'
								AND v.idvcf = :idvcf
					)
					AND 10<(adipose+adrenal+brain+breast+colon+heart+kidney+leukocyte+liver+lung+lymph_node+ovary+prostate+skeletal_muscle+testis+thyroid)
			ORDER	BY g.gene";
			
	// GENES VARIADOS COUNT
    $sqlcantidad = "SELECT	COUNT(*) AS cantidad
			FROM	gxa_tissues AS g 
					JOIN ref_genes AS r ON g.gene = r.gene
					JOIN variants AS v ON v.idvariant = r.idvariant AND v.idvcf = :idvcf";
			
    $result->datos = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
    $q = $app['dbs']['local']->executeQuery($sqlcantidad, $params)->fetch();
    $result->cantidad = ($q['cantidad']) ? $q['cantidad'] : 0;
    
    return $app->json($result);
});


$controller->get('genes/all', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	// MIN/MAX por tejido
    $sql = "SELECT	*
			FROM	`gxa_tissues`";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
    return $app->json($result);
});


$app->mount('/gxa', $controller);