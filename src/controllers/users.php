<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

function generateRandomString($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$controller = $app['controllers_factory'];

$controller->get('/login', function (Request $request) use($app) {
    if($app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
        return $app->redirect('/dashboard');
    }
    return $app['twig']->render('/user/login.html.twig', array('username'=>''));
});

$controller->get('/logout', function () use($app) {
    $app['session']->clear();
	return $app->redirect('/');
});

$controller->get('/activate/{id}', function ($id) use($app) {
    $sql = "SELECT email, status FROM users WHERE SHA1(iduser) = ?";
    $user = $app['dbs']['local']->executeQuery($sql, array($id))->fetch();
    if($user==NULL){
        return $app['twig']->render('/user/activate.html.twig', array(
            'error'=>'Código de activación inválido.'
        ));
    }
    if($user['status']=='active'){
        return $app['twig']->render('/user/activate.html.twig', array(
            'email'=>$user['email'],
            'error'=>'El usuario ya se encuentra activado.'
        ));
    }
    
    $sql = "UPDATE users SET status = 'active' WHERE SHA1(iduser) = ?";
    $rows_affected = $app['dbs']['local']->executeUpdate($sql, array($id));
    return $app['twig']->render('/user/activate.html.twig', array(
        'email'=>$user['email'],
        'message'=>'Activación exitosa!'
    ));
});

$controller->get('/register', function () use($app) {
   return $app['twig']->render('/user/register.html.twig', array('email'=>'', 'name'=>''));
});

$controller->post('/register', function (Request $request) use($app) {
    $email = $request->get('email');
    $name = $request->get('name');
    
    if(empty($email)){
        return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'Correo electrónico no puede estar vacío.'
	    ));
    }
    
    $sql = "SELECT email FROM users WHERE email = ?";
    $user = $app['dbs']['local']->executeQuery($sql, array(strtolower($email)))->fetch();
    if($user!=NULL) {
         return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'Correo electrónico '.$email.' ya se encuentra registrado.'
	    ));
    }

    if(empty($name)){
        return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'El nombre no puede estar vacío.'
	    ));
    }
    
    $password = $request->get('password');
    $password2 = $request->get('password2');
    if(empty($password) || empty($password2)){
        return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'Contraseña no puede estar vacío.'
	    ));
    }
    
    if($password !== $password2){
        return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'Las contraseñas no coinciden.'
	    ));
    }
    
    $terms = $request->get('terms');
    if(empty($password) || empty($terms)){
        return $app['twig']->render('/user/register.html.twig', array(
    	    'email'=>$email,
    	    'name'=>$name,
    	    'message'=>'Debe aceptar los términos y condiciones.'
	    ));
    }
    
    $sql = "INSERT INTO users (email, name, password, status) VALUES (?, ?, ?, 'inactive')";
    $rows_affected = $app['dbs']['local']->executeUpdate($sql, array(strtolower($email), $name, hash('sha256', $password)));
        
    $sql = "SELECT iduser FROM users WHERE email = ?";
    $user = $app['dbs']['local']->executeQuery($sql, array(strtolower($email)))->fetch();
    
    $link = $request->getSchemeAndHttpHost() .'/user/activate/'.hash('sha1', $user['iduser']);
    //$link = '<a href="'.$link.'">'.$link.'</a>';
    
    $message = \Swift_Message::newInstance()
        ->setSubject('[Visual Genoma] Activacion de cuenta')
        ->setFrom(array('visualgenome@gmail.com'))
        ->setTo(array($email))
        ->setBody('Para activar su cuenta de Visual Genoma haga click en el siguiente link: '.$link);
    $app['mailer']->send($message);
        
    return $app['twig']->render('/user/register.html.twig', array(
	    'email'=>'',
	    'name'=>'',
	    'success'=>'Registro exitoso! Una notificación ha sido enviada a su correo electrónico ('.$email.'). para activar su cuenta.',
    ));
});

$controller->get('/forgot', function () use($app) {
   return $app['twig']->render('/user/forgot.html.twig', array(
       'email'=>'',
       'message'=>''
   ));
});

$controller->post('/forgot', function (Request $request) use($app) {
    $email = $request->get('_email');
    if(empty($email)){
        return $app['twig']->render('/user/forgot.html.twig', array(
    	    'email'=>$email,
    	    'message'=>'Correo electrónico no puede estar vacío.'
	    ));
    }
    $sql = "SELECT email FROM users WHERE status='active' AND email = ?";
    $user = $app['dbs']['local']->executeQuery($sql, array(strtolower($email)))->fetch();
    if($user!=NULL) {
    	$password = generateRandomString();
	    
	    $sql = "UPDATE users SET password = ? WHERE email = ?";
	    $rows_affected = $app['dbs']['local']->executeUpdate($sql, array(
	            hash('sha256', $password),
	            strtolower($email)
            )
        );
	    
	    $message = \Swift_Message::newInstance()
            ->setSubject('[proyecto] Password reset')
            ->setFrom(array('visualgenome@gmail.com'))
            ->setTo(array($email))
            ->setBody('Recibe este correo electrónico porque ha solicitado la recuperación de su contraseña. Su nueva contraseña es: '.$password);
        $app['mailer']->send($message);
        
        return $app['twig']->render('/user/forgot.html.twig', array(
    	    'email'=>$email,
    	    'message'=>'Su nueva contraseña ha sido enviada a: '.$email,
	    ));
    }
    return $app['twig']->render('/user/forgot.html.twig', array(
        'email'=>$email,
        'message'=>'La direccíon de correo electrónico no ha sido encontrada en el sistema.'
    ));
});

$controller->get('/profile', function (Request $request) use($app) {
    $user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT name, email, gender, dni, phone, movil, address FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	
    return $app['twig']->render('/user/profile.html.twig', (array) $user);
});


$controller->post('/profile', function (Request $request) use($app) {
    $user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT * FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	
	$result = new stdClass;
	/*
	$result->name = $user['name'];
	$result->email = $user['email'];
	$result->gender = $user['gender'];
	$result->dni = $user['dni'];
	$result->phone = $user['phone'];
	$result->movil = $user['movil'];
	$result->address = $user['address'];
	*/
	
	$result->name = $request->request->get('name');
	$result->email = $request->request->get('email');
	$result->gender = $request->request->get('gender');
	$result->dni = $request->request->get('dni');
	$result->phone = $request->request->get('phone');
	$result->movil = $request->request->get('movil');
	$result->address = $request->request->get('address');
	
	if(empty($result->email) || empty($result->name))
	{
    	$result->error = "El email y el nombre son obligatorios.";
	    return $app['twig']->render('/user/profile.html.twig', (array) $result);
	}
	
	//update user email
	$email = $request->request->get('email');
	if(!empty($email) && strtolower($email)!=$user['email'])
	{
    	$sql = "UPDATE users SET email = :email WHERE iduser = :id";
        $ok = $app['dbs']['local']->executeUpdate($sql, array( 'id' => $user['iduser'], 'email' => strtolower($email) ));
	}
	
    //update user name
	
	$sql = "UPDATE users SET name = :name , gender = :gender , dni = :dni , phone = :phone, movil = :movil , address = :address WHERE iduser = :id";
    $app['dbs']['local']->executeUpdate($sql, array( 
        'id' => $user['iduser'], 
        'name' => $result->name,
        'gender' => $result->gender, 
        'dni' => $result->dni,
        'phone' => $result->phone,
        'movil' => $result->movil,
        'address' => $result->address
        ));

	//update user password
	$password = $request->request->get('password');
	$password1 = $request->request->get('password1');
	$password2 = $request->request->get('password2');
	
	if(!empty($password) || !empty($password1) || !empty($password2))
	{
	    if(hash('sha256', $password) != $user['password'])
	    {
	        $result->error = "Contraseña incorrecta.";
	        return $app['twig']->render('/user/profile.html.twig', (array) $result);
	    }
	    
	    if($password1!=$password2)
	    {
	        $result->error = "Las nuevas contraseñas no coinciden.";
	        return $app['twig']->render('/user/profile.html.twig', (array) $result);
	    }
	    
    	$sql = "UPDATE users SET password = :password WHERE iduser = :id";
        $app['dbs']['local']->executeUpdate($sql, array( 'id' => $user['iduser'], 'password' => hash('sha256', $password1) ));
	}

    $sql = "SELECT * FROM users WHERE iduser = :id";
	$user = $app['dbs']['local']->executeQuery($sql, array("id" => $user['iduser']))->fetch();
	$result->name = $user['name'];
	$result->email = $user['email'];
	$result->gender = $user['gender'];
	$result->dni = $user['dni'];
	$result->phone = $user['phone'];
	$result->movil = $user['movil'];
	$result->address = $user['address'];
    $result->success = "Su perfil ha sido actualizado con éxito.";
    
    return $app['twig']->render('/user/profile.html.twig',  (array) $result);
});

$controller->get('/profile/avatar', function (Request $request) use($app) {
    $user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT avatar FROM users WHERE email = :email";
	$usuario = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(empty($usuario['avatar'])){
	    return $app->sendFile($app['resources_folder'].'/images/default-picture.png');
	}

    $response = new \Symfony\Component\HttpFoundation\Response($usuario['avatar'], 200, array(
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => sizeof($usuario['avatar']),
        'Content-Disposition' => 'attachment; filename="avatar"',
    ));

    return $response;
});

$controller->post('/profile/avatar/upload', function (Request $request) use($app) {
    $user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT avatar FROM users WHERE email = :email";
	$usuario = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();

    $file_bag = $request->files;
    if($file_bag->has('file') && $file_bag->get('file')!=NULL){
        $file = $file_bag->get('file');
        //generate unique name
        $filename = sha1(uniqid());
        //move to uploads
        $file->move($app['temp_folder'] . '/', $filename);
        $originalName = $file->getClientOriginalName();
        //process vcf (runs python script)
        try{
            $imagen_temporal = $app['temp_folder'] . '/' . $filename;
            $binario_contenido = bin2hex(fread(fopen($imagen_temporal, "rb"), filesize($imagen_temporal)));
            
            $sql = "UPDATE users SET avatar = 0x".$binario_contenido." WHERE email = :email";
            $app['dbs']['local']->executeUpdate($sql, array( 
                //'archivo' => $binario_contenido,
                'email' =>  $user->getUsername()
                ));
            
            unlink($imagen_temporal);
            
            return new Response("Archivo cargado correctamente", 200);
        }catch(Exception $e){
            return new Response("Error al cargar el archivo", 500);
        }
    }else{
        return new Response("Error al cargar el archivo (2)", 500);
    }
});

$controller->get('/profile/name', function (Request $request) use($app) {
    $user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT * FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch(); 
	if(!$user){
	    return $app->redirect('/');
	}
    return new Response($user['name'], 200);
});

$controller->post('/setfile/{id}', function ($id) use($app) {
    //get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario desconectado." );
	}
    $sql = "INSERT INTO user_vcf (iduser, idvcf) VALUES (:iduser,:idvcf)";
	$params = array("iduser" => $user['iduser'], "idvcf" => $id);
	$result = $app['dbs']['local']->executeUpdate($sql, $params);	
	return new Response("OK", 200);
});


$app->mount('/user', $controller);