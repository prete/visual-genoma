<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$controller = $app['controllers_factory'];

//genome-mysql.cse.ucsc.edu
$controller->get('/chromInfo', function () use($app) {
    $chroms = "('chr1','chr2','chr3','chr4','chr5','chr6','chr7','chr8','chr9','chr10','chr11','chr12',
                'chr13','chr14','chr15','chr16','chr17','chr18','chr19','chr20','chr21','chr22','chrX','chrY')";
    $sql = "SELECT chrom as chrom, size as size FROM chromInfo WHERE chrom IN ".$chroms;
    $result = $app['dbs']['ucsc.edu']->executeQuery($sql);
    $result = $result->fetchAll(\PDO::FETCH_ASSOC);
    return  $app->json($result);
});

$app->mount('/ucsc', $controller);