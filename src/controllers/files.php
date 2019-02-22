<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$controller = $app['controllers_factory'];

$controller->get('/admin', function() use($app) {
	return $app['twig']->render('/file/admin.html.twig');
});

$controller->get('/realupload', function() use($app) {
	return $app['twig']->render('/file/upload.html.twig');
});

$controller->get('/upload', function() use($app) {
	return $app['twig']->render('/file/upload.html.twig');
	//return $app['twig']->render('/file/fupload.html.twig');
});

$controller->post('/upload', function (Request $request) use ($app){
    $file_bag = $request->files;
    $end_time  = time();
    if($file_bag->has('upload') && $file_bag->get('upload')!=NULL){
        $file = $file_bag->get('upload');
        
        //generate unique name
        $filename = $app['user']['iduser'].'_'.sha1(uniqid());
        
        //move to uploads
        $file->move($app['upload_folder'], $filename);
        $originalName = $file->getClientOriginalName();
        
        $start_time  = time();
        
        //process vcf (runs python script)
        try{
            $command = escapeshellcmd('python3 '.$app['python_script'].' '.$originalName.' '.$app['upload_folder'].$filename);
            exec($command, $out, $status);
            
            if($status==0){
                
                $idvcf = $out[0];
                
            	$sql = "UPDATE  vcf_files 
            	        SET     uploaded_filename = :filename
            	        WHERE   idvcf = :idvcf";
            	$params = array(
            	    "filename" => $filename,
            	    "idvcf" => $idvcf
        	    );
            	$result = $app['dbs']['local']->executeUpdate($sql, $params);
            	
            	$sql = "INSERT INTO user_vcf (iduser, idvcf) VALUES (:iduser,:idvcf)";
            	$params = array(
            	    "iduser" => $app['user']['iduser'],
            	    "idvcf" => $idvcf
        	    );
            	
            	$result = $app['dbs']['local']->executeUpdate($sql, $params);
            	
            	$message = 'Carga de archivo exitosa! (id de archivo = ' . $idvcf . ')';
            	
            	return $app->redirect('/dashboard');
            	
            } else if($status==100){
                $message = 'Error al procesar el archivo. Archivo no encontrado.';
            } else if($status==101){
                $message = 'Error al procesar el archivo. Fromato incorrecto. Se requieren al menos 8 columna.';
            } else if($status==200){
                $message = 'Error al procesar el archivo. Probelmas al conectarse con la base de datos.';
            } else {
                $message = 'Error al procesar el archivo!. '.print_r($out, true);
            }
        }catch(Exception $e){
            $message = 'Error al procesar el archivo! ' . $e->getMessage();
        }
    }else{
        $message = 'Debe seleccionar un archivo.';
    }
    
    $elapsed_time = (time()-$start_time);
    $elapsed_unit = ($elapsed_time<60) ? ' segundos' : ' minutos';
    $elapsed_time = ($elapsed_time>60) ? $elapsed_time : ($elapsed_time/60);
    
    return $app['twig']->render('/file/upload.html.twig', array(
        'message' => $message
        //'elapsed' => number_format($elapsed_time, 2).$elapsed_unit
    ));
});

$controller->get('/upload_progress', function() use($app) {
    $key = 'upload_progress_'.$app['user']['iduser'];
    $result = new stdClass;
    $result->debug = $_SESSION[$key];
    if (!empty($_SESSION[$key])) {
        $current = $_SESSION[$key]["bytes_processed"];
        $total = $_SESSION[$key]["content_length"];
        $result->progress = $current < $total ? ceil($current / $total * 100) : 100;
    }
    else {
        $result->progress = 0; 
    }
    return $app->json($result);
    
});

$controller->get('/download', function() use($app) {
	//get vcfs
	$sql = "SELECT  v.*
	        FROM    vcf_files AS v,
	                user_vcf AS u 
            WHERE   v.idvcf = u.idvcf
                    AND u.iduser = :iduser";
	$params = array("iduser" => $app['user']['iduser']);
	$result = $app['dbs']['local']->executeQuery($sql, $params)->fetchAll(PDO::FETCH_OBJ);
	 
	return $app['twig']->render('/file/download.html.twig', array(
	    'archivos'=>$result
    ));
});

$controller->get('/download/{id}', function ($id) use($app) {
    $params = array("idvcf" => $id);
    $sql = "SELECT * FROM vcf_files WHERE idvcf = :idvcf";
    $vcf = $app['dbs']['local']->executeQuery($sql, $params)->fetch(PDO::FETCH_OBJ);
    
    $file = $app['upload_folder'].$vcf->uploaded_filename;
    if (!file_exists($file)) {
        return $app->abort(404, 'Archivo no encontrado.');
    }
    //return $app->sendFile($file);
    
    $stream = function () use ($file) {
        readfile($file);
    };
    return $app->stream($stream, 200, array(
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => filesize($file),
        'Content-Disposition' => 'attachment; filename="'.$vcf->date.'-'.$vcf->filename.'"'));
});

$app->mount('/file', $controller);