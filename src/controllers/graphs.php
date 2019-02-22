<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

$controller->get('/chrom_bubbles', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	$sql_chrom_genes = "SELECT	f.Gene_ID AS `name`,
								COUNT(1) AS `size`
						FROM	functional_annotations AS f,
								variants AS v
						WHERE	f.idvariant = v.idvariant
								AND v.chrom = :chrom
								AND v.idvcf = :idvcf
						GROUP	BY f.Gene_ID
						ORDER	BY CAST(v.chrom as UNSIGNED INTEGER)";
	foreach($app['bio']->chromosomes as $chrom) {
		$genes = $app['dbs']['local']->executeQuery($sql_chrom_genes, array("idvcf" => $idvcf, "chrom" => $chrom));
    	$result[] = array(
    		'name' => 'Cromosoma '.$chrom,
    		'children' => $genes->fetchAll(\PDO::FETCH_ASSOC),
		);
	}
	$result = array(
		'name'=>'Cromosomas y Genes',
		'children'=>$result
	);
	return $app->json($result);
});

$controller->get('/chrom_donut', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	$sql = "SELECT CONCAT('Cromosoma ',s.chrom) AS `label`, COUNT(s.idsnv) AS `value` FROM snvs AS s WHERE s.idvcf = :idvcf GROUP BY s.chrom ORDER BY CAST(s.chrom as unsigned) ASC";
	$result = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
	$result = $result->fetchAll(\PDO::FETCH_ASSOC);
	return $app->json($result);
});

$controller->get('/sunburst', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	$impact_case = "CASE Annotation_Impact WHEN 'HIGH' THEN 'red' WHEN 'MODERATE' THEN 'orange' WHEN 'MODIFIER' THEN 'yellow' WHEN 'LOW' THEN 'green' END AS `color`";

	$sql_impact = "SELECT f.Annotation_Impact AS `name`, COUNT(f.idsnv) AS `size`, ".$impact_case."  FROM functional_annotations AS f, snvs AS s WHERE f.idsnv=s.idsnv AND s.chrom = :chrom AND s.idvcf = :idvcf GROUP BY f.Annotation_Impact ORDER BY CAST(s.chrom as unsigned) ASC, `size` DESC";
	$sql_chrom_genes = "SELECT f.Gene_ID AS `name`, COUNT(f.idsnv) AS `size` FROM functional_annotations AS f, snvs AS s WHERE f.Annotation_Impact = :impact AND f.idsnv=s.idsnv AND s.chrom = :chrom AND s.idvcf = :idvcf GROUP BY f.Gene_ID ORDER BY CAST(s.chrom as unsigned) ASC, `size` DESC";
	foreach($app['bio']->chromosomes as $chrom) {
		$impact = $app['dbs']['local']->executeQuery($sql_impact, array("idvcf" => $idvcf, "chrom" => $chrom));	
		$impact = $impact->fetchAll(\PDO::FETCH_ASSOC);
		
		for($i=0; $i<count($impact); $i++){
			$genes = $app['dbs']['local']->executeQuery($sql_chrom_genes, array("idvcf" => $idvcf, "chrom" => $chrom, "impact"=> $impact[$i]['name']));
			$impact[$i]['children'] = $genes->fetchAll(\PDO::FETCH_ASSOC);
		}
		
    	$result[] = array(
    		'name' => $chrom,
    		'children' => $impact,
		);
	}
	$result = array(
		'name'=>'VizGENOMA',
		'children'=>$result
	);
	return $app->json($result);
});


$controller->get('/snps_indels', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	$count_indels = "SELECT COUNT(s.idsnv) FROM snvs AS s WHERE (LENGTH(s.ref)>1 OR LENGTH(s.alt)>1) AND s.idvcf=:idvcf";
	$count_snps = "SELECT COUNT(s.idsnv) FROM snvs AS s WHERE LENGTH(s.ref)=1 AND LENGTH(s.alt)=1 AND s.idvcf=:idvcf";
	$sql = "SELECT (".$count_snps.") AS `snps`, (".$count_indels.") AS `indels`";
	$global_stats = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));	
	$global_stats = $global_stats->fetch();

	$result = array(
		'snps' => $global_stats['snps'],
		'indels' => $global_stats['indels'],
		'chroms' => array()
	);

	foreach($app['bio']->chromosomes as $chrom) {
		$sql = "SELECT (".$count_snps." AND s.chrom=:chrom) AS `snps`, (".$count_indels." AND s.chrom=:chrom) AS `indels`";
		$chrom_stats = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf, 'chrom' => $chrom));	
		$chrom_stats = $chrom_stats->fetch();
    	$result['chroms'][] = array(
    		'chrom' => $chrom,
    		'snps' => $chrom_stats['snps'],
    		'indels' => $chrom_stats['indels'],
		);
	}
	return $app->json($result);
});

$controller->get('source/variants/per-chromosome', function(Request $request) use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result = array(
	 		'error' => "Not logged in."
 		);
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result = array(
	 		'error' => "No VCF file found."
 		);
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

	$sql = "SELECT	chrom AS `chromosome`,
					COUNT(1) AS `variants`
			FROM	variants
			WHERE	idvcf = :idvcf
			GROUP	BY chrom
			ORDER	BY CAST(chrom as UNSIGNED INTEGER)";
	$chrarr = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_GROUP);
	
	$result = array();
	foreach($app['bio']->chromosomes as $chr){
		$result[] = array(
			'chromosome' => $chr,
			'variants' => isset($chrarr[$chr]) ? (int)$chrarr[$chr][0]['variants'] : 0
		);
	}
    return $app->json($result);
});


$controller->get('source/variants/per-type', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

	$sql = "SELECT	variant_type,
					COUNT(1) AS `variants`
			FROM	variants
			WHERE	idvcf = :idvcf
			GROUP	BY variant_type
			ORDER	BY variant_type";
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});

$controller->get('source/variants/per-chromosome-per-type', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

	$sql = "SELECT	chrom AS `chromosome`,
					variant_type AS `variant_type`,
					COUNT(1) AS `variants`
			FROM	variants
			WHERE	idvcf = :idvcf
			GROUP	BY chrom, variant_type
			ORDER	BY (chrom AS UNSIGNED INTEGER), variant_type";
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('source/frequencies', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

	$sql = "SELECT	SUM(amr) AS `amr`,
					SUM(eur) AS `eur`,
					SUM(eas) AS `eas`,
					SUM(sas) AS `sas`,
					SUM(afr) AS `afr`
			FROM	frequencies AS f,
					variants AS v
			WHERE	f.idvariant = v.idvariant
					AND v.idvcf = :idvcf";
	$freq = $app['dbs']['local']->executeQuery($sql, $params)->fetch();

	$frequencies = array(
		'amr' => 'Mixto Americano',
		'eur' => 'Europeo',
		'eas' => 'Asiático del Este',
		'sas' => 'Asiático del Sur',
		'afr' => 'Africano',
	);
	
	$result = array();
	foreach($frequencies as $key => $value){
		$f = new stdClass;
		$f->axis = $value;
		$f->value = $freq[$key];
		$result[] = $f;
	}
    return $app->json([$result]);
});


$controller->get('source/nucleotides/pie', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    $sql = "SELECT	COUNT(1)
			 FROM	variants AS v
			 WHERE	v.idvcf = :idvcf
					AND LENGTH(v.ref)=1 
					AND LENGTH(v.alt)=1
					AND v.ref = :nucleotide";
    $result = array();
    foreach($app['bio']->i18n->nucleotides as $key=>$value){
    	 $params['nucleotide'] = $key;
    	 $count = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);
		 $n = new stdClass;	
    	 $n->code = $key;
    	 $n->name = $value;
    	 $n->count = $count;
    	 $result[] = $n;
    }
    return $app->json($result);
});


$controller->get('source/chromosomes/genes/bipartite', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    // GENES
    $sql = "SELECT	v.chrom AS `chromosome`,
    				r.Gene AS `gene`,
    				COUNT(c.idvariant) AS `refs1`,
    				COUNT(1) AS `refs2`,
    				CONCAT_WS('|', v.chrom, v.pos) AS `chrompos`
			 FROM	clinical_variants AS c,
					ref_genes AS r,
					variants AS v
			 WHERE	v.idvcf = :idvcf
					AND v.idvariant = r.idvariant
					AND v.idvariant = c.idvariant
					AND CLINSIG like '%pathogenic%'
			GROUP	BY r.Gene, v.chrom
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_NUM);
    return $app->json($result);
});

$controller->get('source/chromosomes/genes/bubbles', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    // GENES
    $sql = "SELECT	v.chrom AS `name`,
    				COUNT(DISTINCT r.Gene) AS `size`
			 FROM	ref_genes AS r,
					variants AS v
			 WHERE	v.idvcf = :idvcf
					AND v.idvariant = r.idvariant
			GROUP	BY v.chrom
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('source/chromosome/quality/bars', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    // QUALITY
    $sql = "SELECT	v.chrom AS `chromosome`,
    				AVG(qual) AS `avg_quality`,
    				COALESCE(MAX(qual),0) AS `max_quality`,
    				COALESCE(MIN(qual),0) AS `min_quality`
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
			GROUP	BY v.chrom
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('source/filters/pass-fail/pie', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    $sql = "SELECT	COUNT(CASE WHEN filter LIKE 'PASS' THEN 1 END) AS `pass`,
    				COUNT(CASE WHEN filter NOT LIKE 'PASS' THEN 1 END) AS `fail`
			 FROM	variants
			 WHERE	idvcf = :idvcf";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_OBJ);
    return $app->json($result);
});

$controller->get('source/variants/bars', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    // GENES
    $sql = "SELECT	v.chrom AS `chromosome`,
    				COUNT(CASE WHEN LENGTH(v.ref)=1 AND LENGTH(v.alt)=1 THEN 1 END) AS `snvs`,
    				COUNT(CASE WHEN LENGTH(v.ref)!=1 OR LENGTH(v.alt)!=1 THEN 1 END) AS `indels`,
    				COUNT(1) AS  `total`
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
			GROUP	BY v.chrom
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('source/variants/pie', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    $sql = "SELECT	COUNT(CASE WHEN LENGTH(v.ref)=1 AND LENGTH(v.alt)=1 THEN 1 END) AS `snvs`,
    				COUNT(CASE WHEN LENGTH(v.ref)!=1 OR LENGTH(v.alt)!=1 THEN 1 END) AS `indels`
			 FROM	variants AS v
			 WHERE	v.idvcf = :idvcf";
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_OBJ);
    return $app->json($result);
});


$controller->get('source/chromosome/genes/hierarchy', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

	// get available chromosomes
    $sql = "SELECT	DISTINCT v.chrom
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $chromosomes = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN,0);

	$result = new stdClass;
	$result->name = "Chromosomes/Genes";
	$result->children = array();
	foreach($chromosomes as $c){
		$item = new stdClass;
		$item->name = "Cromosoma ".$c;
		$params['chromosome'] = $c;
		$sql = "SELECT	r.gene AS 'name',
						COUNT(1) AS 'size',
						CONCAT('Cromosoma-',v.chrom) AS 'parent'
				FROM	variants AS v,
						ref_genes AS r
				WHERE	v.idvcf = :idvcf
						AND v.idvariant = r.idvariant
						AND v.chrom = :chromosome
				GROUP	BY r.gene, CONCAT('Cromosoma-',v.chrom)
				ORDER	BY r.gene";
    	$item->children = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
		$result->children[] = $item;
	}
    return $app->json($result);
});

$controller->get('source/genes/treemap', function(Request $request) use($app) {
	$result = new stdClass;
	
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	 	$result->error = "Not logged in.";
    	return $app->json($result);
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
	 	$result->error = "No VCF file found.";
    	return $app->json($result);
    }
	$params = array("idvcf" => $vcf['idvcf']);

    // GENES
    $sql = "SELECT	v.chrom AS `chromosome`,
    				r.gene AS `id`,
    				COUNT(1) AS  `count`
			FROM	variants AS v,
					ref_genes AS r
			WHERE	v.idvcf = :idvcf
					AND v.idvariant = r.idvariant
			GROUP	BY v.chrom, r.gene
			ORDER	BY CAST(v.chrom AS UNSIGNED INT), r.gene";
    $resultChildren = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
        // GENES
    $sql = "SELECT	'CROMOSOMA' AS `chromosome`,
    				v.chrom AS `id`,
    				0 AS  `count`
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
			GROUP	BY v.chrom
			ORDER	BY CAST(v.chrom AS UNSIGNED INT)";
    $resultParents = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $parent = new stdClass;
    $parent->chromosome = 'CROMOSOMA';
    $parent->children = array_merge($resultParents, $resultChildren);
    return $app->json($parent);
});


$controller->get('source/variants/clinical/hierarchy', function(Request $request) use($app) {
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
    	$app->abort(400, "VCF no encontrado.");
    }
    
	$params = array('idvcf' => 	$vcf['idvcf']);
	
	//get clinvars
	$sql = "SELECT	*
			FROM	clinical_variants AS c
			WHERE	EXISTS (
						SELECT	*
						FROM	variants AS v
						WHERE	v.idvariant = c.idvariant 
								AND v.idvcf = :idvcf
					)";
	$clinvars = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
	
	$children = array();
	$parents = array();
	foreach($clinvars as $c){
		$sig = explode('|',str_replace('_', ' ', $c['CLINSIG']));
    	$dbn = explode('|',str_replace('_', ' ', $c['CLNDBN']));
    	$acc = explode('|',str_replace('_', ' ', $c['CLNACC']));
    	$dsdb = explode('|',str_replace('_', ' ', $c['CLNDSDB']));
    	$dsdbid = explode('|',str_replace('_', ' ', $c['CLNDSDBID']));
    	foreach($sig as $key=>$val){
			$r = new stdClass;
			$r->id = $dbn[$key];
			$r->name = $dbn[$key];
			$r->parentName = $val;
			$r->parentId = '[PARENT] '.$val;
			$parents['[PARENT] '.$val] = $val;
			
			//cliinvar data
			$r->idvariant = $c['idvariant'];
			$r->CLINSIG = $val;
			$r->CLNDBN = $dbn[$key];
			$r->CLNACC = $acc[$key]; 
			$r->CLNDSDB = $dsdb[$key];
			$r->CLNDSDBID = $dsdbid[$key];
			$r->CLNREVSTAT = $c['CLNREVSTAT'];


			$children[] = $r;
    	}
	}
	$result = $children;
	
	$root = new stdClass;
	$root->id = 'CLINVAR_ROOT';
	$root->name = 'ROOT';
	$root->parentId = '';
	$result[] = $root;
	
	foreach($parents as $key=>$value){
		$parent = new stdClass;
		$parent->name = $value;
		$parent->id = $key;
    	$parent->parentId = 'CLINVAR_ROOT';
    	$result[] = $parent;
	}

    return $app->json($result);
});


$app->mount('/graphs', $controller);