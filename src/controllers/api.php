<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];

/*
* GZipped Content
* if(function_exists('ob_gzhandler')) ob_start('ob_gzhandler'); else ob_start();
* echo $app->json($result);
* ob_end_flush();
*/

$controller->get('/nucleotides/substitution-per-base', function(Request $request) use($app) {
	$idvcf = 1;
	$nucleotides = ['A','G','C','T'];
    $sql = "SELECT COUNT(*) FROM snvs WHERE idvcf = :idvcf AND ref=:nucleotide";
    $result = array('total'=>0);
    foreach($nucleotides as $nucleotide) {
    	$params = array("idvcf" => $idvcf, "nucleotide" => $nucleotide);
    	$count = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN|PDO::FETCH_GROUP, 0);
    	$result[$nucleotide] = $count;
		$result['total'] += $count;
    }
    return $app['twig']->render('/partials/nucleotides/substitution-per-base.html.twig', $result);
});

$controller->get('/1', function(Request $request) use($app) {
	$idvcf = $request->query->get('id');
	
	$sql = "SELECT COUNT( DISTINCT( s.idsnv ) ) AS `size` FROM snvs AS s WHERE s.idvcf=:idvcf";
    $snvs = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    
    $sql = "SELECT AVG( s.qual ) AS `Average` FROM snvs AS s WHERE s.idvcf = :idvcf";
    $qual = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    
    $sql = "SELECT COUNT( DISTINCT( f.Gene_ID ) ) AS `size` FROM functional_annotations AS f, snvs AS s WHERE f.idsnv=s.idsnv AND s.idvcf=:idvcf";
    $genes = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    
    $sql = "SELECT COUNT(c.idclinvar) AS `size` FROM clinical_variations AS c, snvs AS s WHERE c.idsnv=s.idsnv AND s.idvcf=:idvcf";
    $clinvar = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='C') AS `C`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='A'";
    $A = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $A = $A->fetch();
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='C') AS `C`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='G'";
    $G = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $G = $G->fetch();
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='C'";
    $C = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $C = $C->fetch();
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='C') AS `C`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='T'";
    $T = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $T = $T->fetch();
    
   	return $app['twig']->render('/tests/1.html.twig', array(
   		"snvs" => $snvs->fetch()['size'],
   		"qual" => $qual->fetch()['Average'],
   		"genes" => $genes->fetch()['size'],
   		"clinvar" => $clinvar->fetch()['size'],
   		"base_changes" => array(
   			"A" => $A,
   			"G" => $G,
   			"C" => $C,
   			"T" => $T,
   			"total" => $A['size']+$G['size']+$C['size']+$T['size'],
   		)
   	));
});

$controller->get('/genes/relation', function(Request $request) use($app) {
	$idvcf = 2;
	$sql = "SELECT	DISTINCT(Gene_Name)
			FROM	functional_annotations f,
					snvs s
			WHERE	s.idsnv = f.idsnv
					AND s.idvcf = :idvcf";
	$params = array("idvcf" => $idvcf);
	$genes_in_vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	
	$result = new stdClass;
	
	$sql = $sql."	AND s.chrom = :chrom";
	foreach($app['bio']->chromosomes as $chrom) {
		$params = array("idvcf" => $idvcf, "chrom" => $chrom);
		$genes_in_chrom = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
		$genes_row = new stdClass;
		foreach($genes_in_vcf as $gen) {
			$genes_row->$gen = isset($genes_in_chrom[$gen]);
		}
		$result->$chrom = $genes_row;
	}
	return $app->json($result);
});


$controller->get('/genes', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	DISTINCT(Gene_Name)
			FROM	functional_annotations f, snvs s
			WHERE	s.idsnv = f.idsnv AND s.idvcf = :idvcf
			ORDER	BY Gene_Name";
	$params = array("idvcf" => $idvcf);
	$result = new stdClass;
	$result->genes = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	return $app->json($result);
});


$controller->get('/genes/count', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	COUNT(DISTINCT(Gene_Name)) AS `genes`
			FROM	functional_annotations f, snvs s
			WHERE	s.idsnv = f.idsnv AND s.idvcf = :idvcf";
	$params = array("idvcf" => $idvcf);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	return $app->json($result);
});


$controller->get('/genes/per-chromosome', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT DISTINCT(chrom) FROM snvs WHERE idvcf = :idvcf";
	$params = array("idvcf" => $idvcf);
	$chromosomes = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);	

	$sql = "SELECT	DISTINCT(Gene_Name)
			FROM	functional_annotations f, snvs s
			WHERE	s.idsnv = f.idsnv AND s.idvcf = :idvcf AND s.chrom = :chrom";
	
	$result = new stdClass;		
	foreach($chromosomes as $chrom) {
		$params = array("idvcf" => $idvcf, "chrom" => $chrom);
		$result->$chrom = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
	}
	return $app->json($result);
});


$controller->get('/genes/per-chromosome/count', function(Request $request) use($app) {
	$idvcf = 2;
	$sql = "SELECT	s.chrom AS `chr`, COUNT(DISTINCT(Gene_Name)) AS `genes`
			FROM	functional_annotations f, snvs s
			WHERE	s.idsnv = f.idsnv AND s.idvcf = :idvcf
			GROUP	BY s.chrom
			ORDER	BY CAST(s.chrom AS SIGNED INTEGER)";
	$params = array("idvcf" => $idvcf);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
	return $app->json($result);
});


$controller->get('/variant/id/{id}', function($id, Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	*
			FROM	snvs
			WHERE	idsnv = :idsnv";
	$params = array("idsnv" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});

$controller->get('/variants/id/{id}/genes', function($id, Request $request) use($app) {
	$sql = "SELECT	DISTINCT(Gene_Name)
			FROM	functional_annotations AS f,
					variants v
			WHERE	v.idvariant = f.idvariant 
					AND v.idvcf = :idvcf
			ORDER	BY Gene_Name";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});

$controller->get('/variants/id/{id}/refgenes', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	ref_genes
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});

$controller->get('/variants/id/{id}/functional', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	functional_annotations
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});

$controller->get('/variants/id/{id}/predictions', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	predictions
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});

$controller->get('/variants/id/{id}/consequences', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	consequence_annotations
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});

$controller->get('/variants/id/{id}/clinicals', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	clinical_variants
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});

$controller->get('/variants/id/{id}/frequencies', function($id, Request $request) use($app) {
	$sql = "SELECT	*
			FROM	frequencies
			WHERE	idvariant = :idvariant";
	$params = array("idvariant" => $id);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});


$controller->get('/variants', function(Request $request) use($app) {
	$idvcf = 1;
	$offset = intval($request->query->get('offset'));
	$sql = "SELECT	*
			FROM	variants AS v
			WHERE	v.idvcf = :idvcf
			LIMIT	".$offset.", 100";
	$params = array("idvcf" => $idvcf);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('/variants/impact/per-chromosome', function(Request $request) use($app) {
	$idvcf = 2;
	$sql = "SELECT	v.chrom AS chr,
					COUNT(CASE WHEN f.Annotation_Impact='HIGH' THEN 1 END) AS ALTO,
					COUNT(CASE WHEN f.Annotation_Impact='MODERATE' THEN 1 END) AS MODERADO,
					COUNT(CASE WHEN f.Annotation_Impact='MODIFIER' THEN 1 END) AS MODIFICADOR,
					COUNT(CASE WHEN f.Annotation_Impact='LOW' THEN 1 END) AS BAJO
			FROM	variants AS v,
					functional_annotations AS f
			WHERE	v.idvcf = :idvcf
					AND v.idvariant = f.idvariant
			GROUP	BY v.chrom
			ORDER	BY CAST(v.chrom as UNSIGNED INTEGER)";
	$params = array("idvcf" => $idvcf);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('/variants/per-chromosome', function(Request $request) use($app) {
	$idvcf = 1;
    $sql = "SELECT	chrom as `chr`,
    				COUNT(1) AS `variants`
			FROM	variants
			WHERE	idvcf = :idvcf 
			GROUP	BY chrom
			ORDER	BY CAST(chrom AS SIGNED INTEGER)";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('/variants/per-chromosome/count', function(Request $request) use($app) {
	$idvcf = 1;
    $sql = "SELECT	chrom as `chr`,
    				COUNT(1) AS `variants`
			FROM	variants
			WHERE	idvcf = :idvcf 
			GROUP	BY chrom
			ORDER	BY CAST(chrom AS SIGNED INTEGER)";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('/variants/snvs/nucleotides', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	COUNT(CASE WHEN ref='A' THEN 1 END) AS `A`,
					COUNT(CASE WHEN ref='T' THEN 1 END) AS `T`,
					COUNT(CASE WHEN ref='C' THEN 1 END) AS `C`,
					COUNT(CASE WHEN ref='G' THEN 1 END) AS `G`
			FROM	variants
			WHERE	idvcf = :idvcf";
	$params = array("idvcf" => $idvcf);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});


$controller->get('/variants/nucleotides/substitutions', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	COUNT(CASE WHEN alt='A' THEN 1 END) AS `A`,
					COUNT(CASE WHEN alt='T' THEN 1 END) AS `T`,
					COUNT(CASE WHEN alt='C' THEN 1 END) AS `C`,
					COUNT(CASE WHEN alt='G' THEN 1 END) AS `G`
			FROM	snvs
			WHERE	idvcf = :idvcf 
					AND ref = :base";
	$result = new stdClass;
	foreach($app['bio']->nucleotides as $base){
		$params = array("idvcf" => $idvcf, "base" => $base);
		$result->$base = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
	}
	return $app->json($result);
});


$controller->get('/variants/type', function(Request $request) use($app) {
	$idvcf = 1;
	
	//|	SNP 	|	Single-Nucleotide Polymorphism		|	Reference = 'A', Sample = 'C'
	//|	INS 	|	Insertion 							|	Reference = 'A', Sample = 'AGT'
	//|	DEL 	|	Deletion 							|	Reference = 'AC', Sample = 'C'
	//|	MNP 	|	Multiple-nucleotide polymorphism 	|	Reference = 'ATA', Sample = 'GTC'
	//|	MIXED	|	Multiple-nucleotide and an InDel 	|	Reference = 'ATA', Sample = 'GTCAGT' 
	
	$sql = "SELECT	COUNT(CASE WHEN variant_type='DEL' THEN 1 END) AS `DEL`,
					COUNT(CASE WHEN variant_type='INS' THEN 1 END) AS `INS`,
					COUNT(CASE WHEN variant_type='MNV') AS `MNV`,
					COUNT(CASE WHEN variant_type='SNV' THEN 1 END) AS `SNV`,
					COUNT(CASE WHEN variant_type='MIXED' THEN 1 END) AS `MIXED`
			FROM	variants
			WHERE	idvcf = :idvcf";
    $params = array("idvcf" => $idvcf);
    
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});

$controller->get('/snvs/type/per-chromosome/', function(Request $request) use($app) {
	$idvcf = 1;
	$sql = "SELECT	chrom as `chr`, 
					COUNT(CASE WHEN (LENGTH(ref)>1 OR LENGTH(alt)>1) THEN 1 END) AS `indels`, 
					COUNT(CASE WHEN LENGTH(ref)=1 AND LENGTH(alt)=1 THEN 1 END) AS `snps` 
			FROM	snvs 
			WHERE	idvcf = :idvcf
			GROUP	BY chrom
			ORDER	BY CAST(chrom AS SIGNED INTEGER)";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});

$controller->get('/snvs/quality/min-max-avg', function(Request $request) use($app) {
	$idvcf = 1;
    $sql = "SELECT	MAX(qual) AS `max`,
    				MIN(qual) AS `min`,
    				AVG(qual) AS `avg`
			FROM	snvs
			WHERE	idvcf = :idvcf";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetch();
    return $app->json($result);
});

$controller->get('/snvs/quality/min-max-avg/per-chromosome', function(Request $request) use($app) {
	$idvcf = 1;
    $sql = "SELECT	chrom as `chr`,
    				MAX(qual) AS `max`,
    				MIN(qual) AS `min`,
    				AVG(qual) AS `avg`
			FROM	snvs
			WHERE	idvcf = :idvcf
			GROUP	BY chrom
			ORDER	BY CAST(chrom AS SIGNED INTEGER)";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});

$controller->get('/proteins/per-gene', function(Request $request) use($app) {
	$idvcf = 1;
    $sql = "SELECT	chrom as `chr`,
    				MAX(qual) AS `max`,
    				MIN(qual) AS `min`,
    				AVG(qual) AS `avg`
			FROM	snvs
			WHERE	idvcf = :idvcf
			GROUP	BY chrom
			ORDER	BY CAST(chrom AS SIGNED INTEGER)";
    $params = array("idvcf" => $idvcf);
    $result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    return $app->json($result);
});


$controller->get('/frequencies', function(Request $request) use($app) {
	$sql = "SELECT	v.idvcf,
					CAST(SUM(afr) AS DECIMAL(10,6)) AS `afr`,
			 		CAST(SUM(eur) AS DECIMAL(10,6)) AS `eur`, 
					CAST(SUM(eas) AS DECIMAL(10,6)) AS `eas`, 
			 		CAST(SUM(sas) AS DECIMAL(10,6)) AS `sas`, 
				 	CAST(SUM(amr) AS DECIMAL(10,6)) AS `amr`
			FROM	frequencies AS f,
					variants AS v
			WHERE	v.idvariant = f.idvariant
			GROUP	BY v.idvcf";
	$result = $app['dbs']['local']->executeQuery($sql)->fetchAll();
	if(!$result){
		$result = array();
	}
    return $app->json($result);
});


$app->mount('/api', $controller);