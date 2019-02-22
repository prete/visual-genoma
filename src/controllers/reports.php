<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$controller = $app['controllers_factory'];

$controller->get('/variants/csv', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;

	$sql = "SELECT	COUNT(*)
			FROM	variants
			WHERE	idvcf = :idvcf";
	$max = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);
	
    $sql = "SELECT	*
    		FROM	variants
    		WHERE	idvcf = :idvcf
			ORDER	BY	CAST(chrom AS UNSIGNED), pos ASC";

    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $response = new \Symfony\Component\HttpFoundation\Response(
        utf8_decode($app['twig']->render('/reports/variants.csv.twig', (array)$result)),
        200,
        array(
            'Content-Type' => 'application/octet-stream;charset=utf-8',
            'Content-Disposition' => 'attachment; filename="variantes.csv"',
        ));

   	return $response;
});

$controller->get('/clinical/csv', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;

	//get clinvars
	$sql = "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					v.*,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND v.idvcf = :idvcf
			ORDER	BY c.CLINSIG";
	
    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $response = new \Symfony\Component\HttpFoundation\Response(
        utf8_decode($app['twig']->render('/reports/clinical.csv.twig', (array)$result)),
        200,
        array(
            'Content-Type' => 'application/octet-stream;charset=utf-8',
            'Content-Disposition' => 'attachment; filename="informe_clinico.csv"',
        ));

   	return $response;
});

$controller->get('/variants/pdf', function (Request $request) use($app) {

	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;

	$sql = "SELECT	COUNT(*)
			FROM	variants
			WHERE	idvcf = :idvcf";
	$max = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_COLUMN, 0);
	
    $sql = "SELECT	*
    		FROM	variants
    		WHERE	idvcf = :idvcf
			ORDER	BY	CAST(chrom AS UNSIGNED), pos ASC ";

    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $tempfile = $app['temp_folder'] . '/' . sha1(uniqid()) . '.pdf';
    $pdf = $app['snappy.pdf']->generateFromHtml(
        //"<pre>" . $request->getBasePath() . '</pre>',
        $app['twig']->render('/reports/variants.html.pdf.twig', (array)$result), 
        $tempfile,
        array(
                'orientation' => 'landscape', 
                'enable-javascript' => true, 
                //'javascript-delay' => 1000, 
                //'no-stop-slow-scripts' => true, 
                'no-background' => false, 
                'lowquality' => false,
                'page-height' => 297,//210 x 297
                'page-width'  => 210,
                'encoding' => 'utf-8',
                'images' => true,
                'cookie' => array(),
                'dpi' => 150,
                'image-dpi' => 150,
                'enable-external-links' => true,
                'enable-internal-links' => true,
                'footer-center' => 'Página [page]'
            )
        );

    //$respuesta = $app->sendFile($tempfile);
    //return $respuesta;

    $response = new Response(fread(fopen($tempfile, "rb"), filesize($tempfile)));
    unlink($tempfile);
    
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="variantes.pdf"');

    return $response;
});

$controller->get('/clinical/pdf', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;

	//get clinvars
	$sql = "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					v.*,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND v.idvcf = :idvcf
			ORDER	BY c.CLINSIG";
	
    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $tempfile = $app['temp_folder'] . '/' . sha1(uniqid()) . '.pdf';
    $pdf = $app['snappy.pdf']->generateFromHtml(
        //"<pre>" . $request->getBasePath() . '</pre>',
        $app['twig']->render('/reports/clinical.html.pdf.twig', (array)$result), 
        $tempfile,
        array(
                'orientation' => 'landscape', 
                'enable-javascript' => true, 
                //'javascript-delay' => 1000, 
                //'no-stop-slow-scripts' => true, 
                'no-background' => false, 
                'lowquality' => false,
                'page-height' => 297,//210 x 297
                'page-width'  => 210,
                'encoding' => 'utf-8',
                'images' => true,
                'cookie' => array(),
                'dpi' => 150,
                'image-dpi' => 150,
                'enable-external-links' => true,
                'enable-internal-links' => true,
                'footer-center' => 'Página [page]'
            )
        );

    //$respuesta = $app->sendFile($tempfile);
    //return $respuesta;

    $response = new Response(fread(fopen($tempfile, "rb"), filesize($tempfile)));
    unlink($tempfile);
    
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="informe_clinico.pdf"');

    return $response;
});

$controller->get('/full/pdf', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;

	//get clinvars
	$sql = "SELECT	c.idvariant AS idvariant,
					CLINSIG AS significance,
					CLNDBN AS disease_name,
					CLNACC AS accession,
					CLNDSDB AS disease_database_name,
					CLNDSDBID AS disease_database_id,
					CLNREVSTAT AS review_status,
					v.*,
					(SELECT Gene FROM ref_genes AS r WHERE r.idvariant = v.idvariant LIMIT 1) AS gene
			FROM	clinical_variants AS c,
					variants AS v
			WHERE	v.idvariant = c.idvariant 
					AND v.idvcf = :idvcf
			ORDER	BY c.CLINSIG";
	
    $result->records = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $sql = "SELECT	*
    		FROM	variants
    		WHERE	idvcf = :idvcf
			ORDER	BY	CAST(chrom AS UNSIGNED), pos ASC ";

    $result->records2 = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll();
    
    $tempfile = $app['temp_folder'] . '/' . sha1(uniqid()) . '.pdf';
    $pdf = $app['snappy.pdf']->generateFromHtml(
        //"<pre>" . $request->getBasePath() . '</pre>',
        $app['twig']->render('/reports/full.html.pdf.twig', (array)$result), 
        $tempfile,
        array(
                'orientation' => 'landscape', 
                'enable-javascript' => true, 
                //'javascript-delay' => 1000, 
                //'no-stop-slow-scripts' => true, 
                'no-background' => false, 
                'lowquality' => false,
                'page-height' => 297,//210 x 297
                'page-width'  => 210,
                'encoding' => 'utf-8',
                'images' => true,
                'cookie' => array(),
                'dpi' => 150,
                'image-dpi' => 150,
                'enable-external-links' => true,
                'enable-internal-links' => true,
                'footer-center' => 'Página [page]'
            )
        );

    //$respuesta = $app->sendFile($tempfile);
    //return $respuesta;

    $response = new Response(fread(fopen($tempfile, "rb"), filesize($tempfile)));
    unlink($tempfile);
    
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="resumen_completo.pdf"');

    return $response;
});


$controller->get('/ancestry/csv', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;
    $jsonfile = json_decode(file_get_contents($request->getSchemeAndHttpHost() . "/resources/ancestry/ancestry.json"), true);
    foreach($jsonfile as $p){
        $pop = new stdClass;
        $pop->continente = $p['pop']['super'];
        $pop->etnia = $p['pop']['etnia'];
        $pop->similitud = round($p['rawfreq']*100,2) . '%';
        $pop->freq = $p['rawfreq'];
        $pop->lng = $p['pos'][0];
        $pop->lat = $p['pos'][1];
        $result->population[] = $pop;
    }
    usort($result->population, function ($a, $b){
        $pos_a = $a->freq;
        $pos_b = $b->freq;
        return ($pos_a>$pos_b)?-1:1;
    });
    
    $response = new \Symfony\Component\HttpFoundation\Response(
        utf8_decode($app['twig']->render('/reports/ancestry.csv.twig', (array)$result)),
        200,
        array(
            'Content-Type' => 'application/octet-stream;charset=utf-8',
            'Content-Disposition' => 'attachment; filename="informe_ancestria.csv"',
        ));

   	return $response;
});


$controller->get('/ancestry/pdf', function (Request $request) use($app) {
	if(!$app['user']['has_vcf']){
    	return $app->redirect('/novcf');
    }
	$params = array("idvcf" => $app['user']['idvcf']);

	$result = new stdClass;
    $jsonfile = json_decode(file_get_contents($request->getSchemeAndHttpHost() . "/resources/ancestry/ancestry.json"), true);
    foreach($jsonfile as $p){
        $pop = new stdClass;
        $pop->continente = $p['pop']['super'];
        $pop->etnia = $p['pop']['etnia'];
        $pop->similitud = round($p['rawfreq']*100,2) . '%';
        $pop->freq = $p['rawfreq'];
        $pop->lng = $p['pos'][0];
        $pop->lat = $p['pos'][1];
        $result->population[] = $pop;
    }
    usort($result->population, function ($a, $b){
        $pos_a = $a->freq;
        $pos_b = $b->freq;
        return ($pos_a>$pos_b)?-1:1;
    });
    
    
    $result->world = file_get_contents($request->getSchemeAndHttpHost() . "/resources/ancestry/world-110m.json");
    $tempfile = $app['temp_folder'] . '/' . sha1(uniqid()) . '.pdf';
    $pdf = $app['snappy.pdf']->generateFromHtml(
        //"<pre>" . $request->getBasePath() . '</pre>',
        $app['twig']->render('/reports/ancestry.html.pdf.twig', (array)$result), 
        $tempfile,
        array(
                'orientation' => 'landscape', 
                'enable-javascript' => true, 
                'javascript-delay' => 2000, 
                'no-stop-slow-scripts' => true, 
                'no-background' => false, 
                'lowquality' => false,
                'page-height' => 297,//210 x 297
                'page-width'  => 210,
                'encoding' => 'utf-8',
                'images' => true,
                'cookie' => array(),
                'dpi' => 150,
                'image-dpi' => 150,
                'enable-external-links' => true,
                'enable-internal-links' => true,
                'footer-center' => 'Página [page]'
            )
        );
        
    $response = new Response(fread(fopen($tempfile, "rb"), filesize($tempfile)));
    unlink($tempfile);
    
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="informe_ancestria.pdf"');

    return $response;
});

$app->mount('/reports', $controller);