<?php

use Symfony\Component\HttpFoundation\Response;
/*
//error handling
$app->error(function (\Exception $e, $code) {
    switch ($code) {
        case 404:
            $message = 'The requested page could not be found.';
            break;
        default:
            $message = 'Fuck this! Something went terribly wrong.';
    }
    return new Response($message, $code);
});
*/
//include controllers
include __DIR__ . '/../src/controllers/views.php';
include __DIR__ . '/../src/controllers/users.php';
include __DIR__ . '/../src/controllers/files.php';
include __DIR__ . '/../src/controllers/api.php';
include __DIR__ . '/../src/controllers/ucsc.php';
include __DIR__ . '/../src/controllers/vcf.php';
include __DIR__ . '/../src/controllers/chromosomes.php';
include __DIR__ . '/../src/controllers/variants.php';
include __DIR__ . '/../src/controllers/graphs.php';
include __DIR__ . '/../src/controllers/gxa.php';
include __DIR__ . '/../src/controllers/help.php';
include __DIR__ . '/../src/controllers/admin.php';
include __DIR__ . '/../src/controllers/reports.php';
