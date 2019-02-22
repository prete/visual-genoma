<?php

$app['bio']->i18n = new stdClass;

$app['bio']->i18n->nucleotides = array(
    'A' => 'Adenina',
    'G' => 'Guanina',
    'C' => 'Citosina',
    'T' => 'Timina',
);

//Variant impact 
//SnpEff provides a simple assessment of the putative impact of the variant (e.g. HIGH, MODERATE or LOW impact).
//http://snpeff.sourceforge.net/SnpEff_manual.html
$app['bio']->i18n->annotationImpact = array(
    'HIGH' => 'ALTO',
    'MODERATE' => 'MODERADO',
    'LOW' => 'BAJO',
    'MODIFIER' => 'MODIFICADOR',
);

//Options for clinical significance
//http://www.ncbi.nlm.nih.gov/clinvar/docs/clinsig/
$app['bio']->i18n->clinicalSignificance = array(
    'Benign' => 'BENIGNA',
    'Likely benign' => 'PROBABLEMENTE BENIGNA',
    'Uncertain significance' => 'SIGNIFICADO INCIERTO',
    'Likely pathogenic' => 'PROBABLEMENTE PATOGENA',
    'Pathogenic' => 'PATOGENA',
    'drug response' => 'RESPUESTA A DROGAS',
    'association' => 'ASOCIACION',
    'risk factor' => 'AUMENTA RIESGO',
    'protective' => 'PROTECTORA',
    'Affects' => 'AFFECTA',
    'conflicting data from submitters' => 'DATOS CONTRADICTORIOS',
    'other' => 'OTRO',
    'not provided' => 'NO PROVISTO',
);

