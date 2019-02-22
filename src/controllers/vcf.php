<?php

use Symfony\Component\HttpFoundation\Request;

$controller = $app['controllers_factory'];
     /*
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='C') AS `C`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='A'";
    $A = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $A = $A->fetch(\PDO::FETCH_ASSOC);
   
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='C') AS `C`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='G'";
    $G = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $G = $G->fetch(\PDO::FETCH_ASSOC);
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='T') AS `T`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='C'";
    $C = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $C = $C->fetch(\PDO::FETCH_ASSOC);
    
    $sql = "SELECT
				COUNT(*) AS `size`,
				COUNT(alt='G') AS `G`,
    			COUNT(alt='C') AS `C`,
				COUNT(alt='A') AS `A`
			 FROM snvs 
			 WHERE idvcf = :idvcf AND ref='T'";
    $T = $app['dbs']['local']->executeQuery($sql, array("idvcf" => $idvcf));
    $T = $T->fetch(\PDO::FETCH_ASSOC);
    */

$controller->get('/summary', function(Request $request) use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
	
	//get vcf
	$sql = "SELECT * FROM user_vcf WHERE iduser = :iduser";
	$vcf = $app['dbs']['local']->executeQuery($sql, array("iduser" => $user['iduser']))->fetch();
	if(!$vcf){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $vcf['idvcf']);
    
    $result = new stdClass;
    
    $sql = "SELECT	COUNT(1) AS `total`,
    				COUNT(CASE WHEN LENGTH(ref)=1 AND LENGTH(alt)=1 THEN 1 END) AS `snv`,
					AVG(qual) AS `avg_qual`
			 FROM	variants
			 WHERE	idvcf = :idvcf";
    $result->variants = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_OBJ);
    
    // GENES
    $sql = "SELECT	r.Gene AS `GENE`, 
    				c.idvariant,
    				c.CLNDBN
			 FROM	ref_genes AS r,
					variants AS v,
					clinical_variants AS c
			 WHERE	v.idvcf = :idvcf
					AND v.idvariant = r.idvariant
					AND v.idvariant = c.idvariant
					AND c.CLINSIG like '%pathogenic%'
			GROUP BY r.Gene, c.idvariant, c.CLNDBN";
    $result->clinvar = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    // NUCLEOTIDES
    $sql = "SELECT	COUNT(1)
			 FROM	variants AS v
			 WHERE	v.idvcf = :idvcf
					AND LENGTH(v.ref)=1 
					AND LENGTH(v.alt)=1";
	$snvs = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);				
    
    $sql = "SELECT	COUNT(1)
			 FROM	variants AS v
			 WHERE	v.idvcf = :idvcf
					AND LENGTH(v.ref)=1 
					AND LENGTH(v.alt)=1
					AND v.ref = :nucleotide";
    $result->nucleotides = array();
    foreach($app['bio']->i18n->nucleotides as $key=>$value){
		 $params['nucleotide'] = $key;
		 $count = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);
    	 $n = new stdClass;	
    	 $n->code = $key;
    	 $n->name = $value;
    	 $n->count = $count;
    	 $n->percentage = ($count/$snvs) * 100;
    	 $result->nucleotides[$key] = $n;
    }
    
    // FILTERS
    $sql = "SELECT	id, 
    				description
			FROM	vcf_meta
			WHERE	idvcf = :idvcf
					AND type like 'FILTER'
			ORDER	BY id";
    $result->filters = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
   	return $app['twig']->render('/vcf_summary.html.twig', (array) $result );
});

$app->mount('/vcf', $controller);