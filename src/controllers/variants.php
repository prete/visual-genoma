<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

$controller->get('/', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);
	
	$result = new stdClass;

	$sql = "SELECT	COUNT(*)
			FROM	variants
			WHERE	idvcf = :idvcf";
	$max = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);
	
	//datos del paginado
	$page_size = 50;
	$max_page = ceil($max/$page_size);
	$page = $request->query->get('page');
	$page = is_numeric($page) ? (int)$page : 1;
	$page = min($page, $max_page);
    
    $sql = "SELECT	*
    		FROM	variants
    		WHERE	idvcf = :idvcf
			ORDER	BY	CAST(chrom AS UNSIGNED), pos ASC LIMIT ".( $page_size * ($page - 1) ).",".$page_size;

    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    $result->page = $page;
    $result->has_next = $page < $max_page;
   	$result->has_prev = $page>1;
   	$result->page_indicator = $page;//($offset+1)."-".$limit;
    $result->total = $max_page;
    
   	return $app['twig']->render('/variants/variants.html.twig', (array)$result);
});

$controller->get('/id/{id}', function($id) use($app) {
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
    
   	$result = new stdClass;
   	
	$sql = "SELECT	*,
					(SELECT name FROM cytobands AS c WHERE c.chromosome = v.chrom AND v.pos BETWEEN c.start and c.end) AS chrom_zone
			FROM	variants AS v
			WHERE	idvcf = :idvcf 
					AND idvariant = :idvariant";
	$params = array("idvcf" => $vcf['idvcf'], "idvariant" => $id);
    $result->variant = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    if(!$result->variant){
    	$app->abort(400, "Variante no encontrada.");
    }
    $params = array("idvariant" => $id);
    
    //get functional annotations
    $result->functionalAnnotations = array();
    $annotations = $app['dbs']['local']->executeQuery("SELECT Annotation as annotation, Annotation_Impact AS impact, Gene_Name AS name FROM functional_annotations WHERE idvariant = :idvariant GROUP BY Annotation, Annotation_Impact, Gene_Name ORDER BY Gene_Name", $params)->fetchAll();
    foreach($annotations as $a){
    	if(!isset($result->functionalAnnotations[$a['name']])){
			$result->functionalAnnotations[$a['name']] = new stdClass;
    		$result->functionalAnnotations[$a['name']]->gene = $a['name'];
    		$result->functionalAnnotations[$a['name']]->annotations = array();
    	}
		$b = new stdClass;
		$b->annotation = explode('&',str_replace('_', ' ', $a['annotation']));
		$b->impact = $app['bio']->i18n->annotationImpact[$a['impact']];
		$result->functionalAnnotations[$a['name']]->annotations[] = $b;
    }
    
    //get consequence annotations
    $result->consequenceAnnotations = array();
    $annotations = $app['dbs']['local']->executeQuery("SELECT Consequence as annotation, IMPACT AS impact, SYMBOL AS name, BIOTYPE AS biotype FROM consequence_annotations WHERE idvariant = :idvariant GROUP BY Consequence, IMPACT, SYMBOL, BIOTYPE ORDER BY SYMBOL", $params)->fetchAll();
    foreach($annotations as $a){
    	if(!isset($result->consequenceAnnotations[$a['name']])){
			$result->consequenceAnnotations[$a['name']] = new stdClass;
    		$result->consequenceAnnotations[$a['name']]->gene = $a['name'];
    		$result->consequenceAnnotations[$a['name']]->annotations = array();
    	}
		$b = new stdClass;
		$b->annotation = explode('&',str_replace('_', ' ', $a['annotation']));
		$b->impact = $app['bio']->i18n->annotationImpact[$a['impact']];
		$b->biotype = $app['bio']->i18n->annotationImpact[$a['biotype']];
		$result->consequenceAnnotations[$a['name']]->annotations[] = $b;
    }
    
    //get clinical annotations
    $result->clinicalAnnotations = array();
    $result->clinicalAnnotations = $app['dbs']['local']->executeQuery("SELECT CLINSIG AS significance, CLNDBN as name FROM clinical_variants WHERE idvariant = :idvariant GROUP BY CLNDBN, CLINSIG ORDER BY CLINSIG", $params)->fetchAll(PDO::FETCH_OBJ);
    /*
    foreach($clinvar as $clin){
    	$sig = explode('|',str_replace('_', ' ', $clin['significance']));
    	$dbn = explode('|',str_replace('_', ' ', $clin['name']));
    	foreach($sig as $key=>$val){
			$c = new stdClass;
			$c->significance = $app['bio']->i18n->clinicalSignificance[$val];
			$c->name = $dbn[$key];
			$result->clinicalAnnotations[] = $c;
    	}
    }
    */    
    //get predictions
    $result->predictions = $app['dbs']['local']->executeQuery("SELECT * FROM predictions WHERE idvariant = :idvariant", $params)->fetchAll();
    
    //get extras
    $result->extras = $app['dbs']['local']->executeQuery("SELECT * FROM extras WHERE idvariant = :idvariant", $params)->fetch();
    
    
    //set external references
    $result->externalReferences = new stdClass;
    
    //get genes
    $ncbi = $app['dbs']['local']->executeQuery("SELECT Gene_Name AS gen, Gene_ID AS ncbi_id FROM functional_annotations WHERE idvariant = :idvariant AND LENGTH(Gene_Name)>0 GROUP BY Gene_Name, Gene_ID ORDER BY Gene_Name", $params)->fetchAll();
    $ensemble = $app['dbs']['local']->executeQuery("SELECT SYMBOL AS gen, Gene AS ensembl_id FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(SYMBOL)>0 GROUP BY SYMBOL, Gene  ORDER BY SYMBOL", $params)->fetchAll();
    $result->externalReferences->genes = array();
    foreach($ncbi as $item){
    	$result->externalReferences->genes[$item['gen']] = new stdClass;
    	$result->externalReferences->genes[$item['gen']]->name = $item['gen'];
    	$result->externalReferences->genes[$item['gen']]->ncbi = $item['ncbi_id'];
    }
    foreach($ensemble as $item){
    	if(!isset($result->genes[$item['gen']])){
    		$result->externalReferences->genes[$item['gen']] = new stdClass;
    		$result->externalReferences->genes[$item['gen']]->name = $item['gen'];
    	}
		$result->externalReferences->genes[$item['gen']]->ensembl = $item['ensembl_id'];
    }
    
    //get nucleotides
    $result->externalReferences->features = new stdClass;
    $result->externalReferences->features->ncbi = $app['dbs']['local']->executeQuery("SELECT DISTINCT(Feature_ID) FROM functional_annotations WHERE idvariant = :idvariant AND LENGTH(Feature_ID)>0 ORDER BY Feature_ID", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    $result->externalReferences->features->ensembl = $app['dbs']['local']->executeQuery("SELECT DISTINCT(Feature) FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(Feature)>0 ORDER BY Feature", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	
	//get proteins
	$result->externalReferences->proteins = new stdClass;
    $result->externalReferences->proteins->ensembl = $app['dbs']['local']->executeQuery("SELECT DISTINCT(ENSP) FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(ENSP)>0 ORDER BY ENSP", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    $result->externalReferences->proteins->uniparc = $app['dbs']['local']->executeQuery("SELECT DISTINCT(UNIPARC) FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(UNIPARC)>0 ORDER BY UNIPARC", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    $result->externalReferences->proteins->swissprot = $app['dbs']['local']->executeQuery("SELECT DISTINCT(SWISSPROT) FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(SWISSPROT)>0 ORDER BY SWISSPROT", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	$result->externalReferences->proteins->ccds = $app['dbs']['local']->executeQuery("SELECT DISTINCT(CCDS) FROM consequence_annotations WHERE idvariant = :idvariant AND LENGTH(CCDS)>0 ORDER BY CCDS", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	
	//get clinvars
	$result->externalReferences->clinical = array();
	$clinical =  $app['dbs']['local']->executeQuery("SELECT DISTINCT(CLNACC) FROM clinical_variants WHERE idvariant = :idvariant ORDER BY CLNACC", $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	foreach($clinical as $item){
		foreach(explode("|", $item) as $i){
			if(in_array($i, $result->externalReferences->clinical)){
				continue;
			}else{
				$result->externalReferences->clinical[] = $i;
			}
		}
	}

    return $app['twig']->render('/variants/variant.html.twig', (array)$result);
});


$controller->get('/clinical', function(Request $request) use($app) {
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
    
	$params = array('idvcf' => 	$vcf['idvcf']);
	
	//count clinvars
	$sql = "SELECT	COUNT(CASE WHEN c.CLINSIG like 'benign' THEN 1 END) AS benign,
					COUNT(CASE WHEN c.CLINSIG like 'likely_benign' THEN 1 END) AS likely_benign,
					COUNT(CASE WHEN c.CLINSIG like 'likely_pathogenic' THEN 1 END) AS likely_pathogenic,
					COUNT(CASE WHEN c.CLINSIG like 'pathogenic' THEN 1 END) AS pathogenic,
					COUNT(CASE WHEN c.CLINSIG like 'uncertain_significance' THEN 1 END) AS uncertain,
					COUNT(CASE WHEN c.CLINSIG NOT LIKE '%benign%' AND c.CLINSIG NOT LIKE '%pathogenic%' AND c.CLINSIG NOT LIKE '%uncertain%' THEN 1 END) AS other
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND v.idvcf = :idvcf";
	$size_clinvars = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_OBJ);
	
	//get clinvars
	$sql = "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND v.idvcf = :idvcf";
	$clinvars = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
	usort($clinvars, "compareVariants");
	
   	return $app['twig']->render('/variants/clinical.html.twig', array(
   		'records' => $clinvars,
   		'size'  => $size_clinvars
   	));
});

function compareVariants($a, $b) {
    return strcmp($a->significance, $b->significance);
}

function clinical_variants_sql() {
	return "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene,
					CASE 
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%HIGH%' THEN 'HIGH'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%MODERATE%' THEN 'MODERATE'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%LOW%' THEN 'LOW'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%MODIFIER%' THEN 'MODIFIER'
					END AS `impact`
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND c.CLINSIG like :clinsig
					AND v.idvcf = :idvcf";
}

$controller->get('/clinical/benign', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array(
		'idvcf' => 	$app['user']['idvcf'],
		'clinsig' => 'benign'
	);
	
	$clinvars = $app['dbs']['local']->executeQuery(clinical_variants_sql(), $params)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('/variants/benign.html.twig', array(
   		'records'=>$clinvars
   	));
});


$controller->get('/clinical/likely_benign', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array(
		'idvcf' => 	$app['user']['idvcf'],
		'clinsig' => 'likely_benign'
	);
	
	$clinvars = $app['dbs']['local']->executeQuery(clinical_variants_sql(), $params)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('/variants/likely_benign.html.twig', array(
   		'records'=>$clinvars
   	));
});

$controller->get('/clinical/likely_pathogenic', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array(
		'idvcf' => 	$app['user']['idvcf'],
		'clinsig' => 'likely_pathogenic'
	);
	
	$clinvars = $app['dbs']['local']->executeQuery(clinical_variants_sql(), $params)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('/variants/likely_pathogenic.html.twig', array(
   		'records'=>$clinvars
   	));
});

$controller->get('/clinical/pathogenic', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array(
		'idvcf' => 	$app['user']['idvcf'],
		'clinsig' => 'pathogenic'
	);
	
	$clinvars = $app['dbs']['local']->executeQuery(clinical_variants_sql(), $params)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('/variants/pathogenic.html.twig', array(
   		'records'=>$clinvars
   	));
});

$controller->get('/clinical/other', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array('idvcf' => 	$app['user']['idvcf']);
	
	//get clinvars
	$sql = "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene,
					CASE 
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%HIGH%' THEN 'HIGH'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%MODERATE%' THEN 'MODERATE'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%LOW%' THEN 'LOW'
						WHEN (SELECT GROUP_CONCAT(Annotation_Impact) FROM functional_annotations AS f WHERE f.idvariant = v.idvariant GROUP BY idvariant) LIKE '%MODIFIER%' THEN 'MODIFIER'
					END AS `impact`
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND c.CLINSIG not like '%benign%' 
					AND c.CLINSIG not like '%pathogenic%'
					AND c.CLINSIG not like '%uncertain_significance%' 
					AND v.idvcf = :idvcf";
	$clinvars = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
   	return $app['twig']->render('/variants/other.html.twig', array(
   		'records'=>$clinvars
   	));
});

$controller->get('/clinical/uncertain', function(Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
    
	$params = array(
		'idvcf' => 	$app['user']['idvcf'],
		'clinsig' => 'uncertain_significance'
	);
	
	$clinvars = $app['dbs']['local']->executeQuery(clinical_variants_sql(), $params)->fetchAll(PDO::FETCH_OBJ);
   	
   	return $app['twig']->render('/variants/uncertain.html.twig', array(
   		'records'=>$clinvars
   	));
});

/*
$controller->get('/frequencies', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	$page_number = $request->query->get('page_number');
	if($page_number == NULL){
		$page_number = 1;
	}
	$page_size = 20;
	$offset = $page_size * ($page_number-1);
	$limit = $offset + $page_size;

	$total_query = "SELECT COUNT(*) as total_records FROM frequencies WHERE idvcf= :idvcf";
	$total_records = $app['dbs']['local']->executeQuery($total_query, array("idvcf" => $idvcf))->fetch()['total_records'];
	
	$last_page = (int)($total_records / $page_size);
	$pages = range(1, $last_page);
	
	$sql = "SELECT f.* FROM frequencies AS f, snvs AS s WHERE f.idsnv = s.idsnv AND s.idvcf = :idvcf LIMIT ".$offset.",".$limit;
    $result = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $result = $result->fetchAll(\PDO::FETCH_ASSOC);
   	return $app['twig']->render('/vcf/frequencies.html.twig', array(
   		"table_records" => $result,
   		"table_paginig" => $pages,
   		"total_records" => $total_records,
   	));
});
*/

$controller->get('/track', function(Request $request) use($app) {
	$chr = $request->query->get('chr');
	$start = $request->query->get('start');
	$end = $request->query->get('end');
	
    $params = array(
    	"idvcf" => $app['user']['idvcf'],
    	"chr" => $chr,
    	"start" => $start,
    	"end" => $end
	);

	$sql = "SELECT	`id` AS rsid,
					`idvariant` AS `id`,
					`chrom` AS `seq_region_name`,
					`variant_type` AS `variant_type`,
					`pos` AS `start`,
					`pos` AS `end`,
					`alt` AS `alt_allele`,
					`ref` AS `ref_allele`,
					(
						SELECT	GROUP_CONCAT(Annotation_Impact)
        				FROM	functional_annotations AS f
        				WHERE	f.idvariant = v.idvariant
        				GROUP	BY idvariant
    				) AS `impact`
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
					AND v.chrom = :chr
					AND v.pos BETWEEN :start AND :end
			ORDER	BY v.pos";
    $variants = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
    
    return $app->json($variants);
});


$app->mount('/variants', $controller);