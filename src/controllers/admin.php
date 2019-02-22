<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$controller = $app['controllers_factory'];

$controller->get('/', function(Request $request) use($app) {
	//get user
	$user = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email AND FIND_IN_SET('ROLE_ADMIN',roles)>0 ";
	$user = $app['dbs']['local']->executeQuery($sql, array("email" => $user->getUsername()))->fetch();
	if(!$user){
	    $app->abort(401, "Usuario no autorizado." );
	}
	
    $result = [];
    
    	//get clinvars
	$sql = "SELECT * FROM users";
			
	$result = $app['dbs']['local']->executeQuery($sql)->fetchAll(PDO::FETCH_OBJ);
	
   	return $app['twig']->render('/admin/index.html.twig', array(
   		'usuarios'=>$result
   	));
});

$controller->get('/edit/{id}', function (Request $request, $id) use($app) {
    
    //get user
	$admin = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email AND FIND_IN_SET('ROLE_ADMIN',roles)>0 ";
	$admin = $app['dbs']['local']->executeQuery($sql, array("email" => $admin->getUsername()))->fetch();
	if(!$admin){
	    $app->abort(401, "Usuario no autorizado." );
	}
	
	if(!$id){
	    $app->abort(400, "Usuario no encontrado." );
	}
	
    $sql = "SELECT name, email, gender, dni, phone, movil, address, status, FIND_IN_SET('ROLE_ADMIN',roles) as roladmin FROM users WHERE iduser = :id";
	$user = $app['dbs']['local']->executeQuery($sql, array("id" => $id))->fetch();
	
	$user['active'] = ($user['status'] == 'active');
	$user['isadmin'] = ($user['roladmin'] > 0);
	$user['id'] = $id;
	
    return $app['twig']->render('/admin/edit.html.twig', (array) $user);
});

$controller->post('/edit/{id}', function (Request $request, $id) use($app) {
    
    //get user
	$admin = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email AND FIND_IN_SET('ROLE_ADMIN',roles)>0 ";
	$admin = $app['dbs']['local']->executeQuery($sql, array("email" => $admin->getUsername()))->fetch();
	if(!$admin){
	    $app->abort(401, "Usuario no autorizado." );
	}
	
	if(!$id){
	    $app->abort(400, "Usuario no encontrado." );
	}
	
    $sql = "SELECT name, email, gender, dni, phone, movil, address, status, FIND_IN_SET('ROLE_ADMIN',roles) as roladmin  FROM users WHERE iduser = :id";
	$user = $app['dbs']['local']->executeQuery($sql, array("id" => $id))->fetch();
	
	$result = new stdClass;

	$result->id = $id;
	$result->name = $request->request->get('name');
	$result->email = $request->request->get('email');
	$result->gender = $request->request->get('gender');
	$result->dni = $request->request->get('dni');
	$result->phone = $request->request->get('phone');
	$result->movil = $request->request->get('movil');
	$result->address = $request->request->get('address');
	$active = $request->request->get('active');
	$result->active = isset($active);
	$isadmin = $request->request->get('isadmin');
	$result->isadmin = isset($isadmin);
	
	if(empty($result->email) || empty($result->name))
	{
    	$result->error = "El email y el nombre son obligatorios.";
	    return $app['twig']->render('/admin/edit.html.twig', (array) $result);
	}
	
	//update user email
	$email = $request->request->get('email');
	if(!empty($email) && strtolower($email)!=$user['email'])
	{
    	$sql = "UPDATE users SET email = :email WHERE iduser = :id";
        $ok = $app['dbs']['local']->executeUpdate($sql, array( 'id' => $id, 'email' => strtolower($email) ));
	}
	
    //update user name
	
	$sql = "UPDATE users SET name = :name , gender = :gender , dni = :dni , 
			phone = :phone, movil = :movil , address = :address, status = :status,
			roles = :roles  WHERE iduser = :id";
    $app['dbs']['local']->executeUpdate($sql, array( 
        'id' => $id, 
        'name' => $result->name,
        'gender' => $result->gender, 
        'dni' => $result->dni,
        'phone' => $result->phone,
        'movil' => $result->movil,
        'address' => $result->address,
        'status' => ($result->active) ? 'active' : 'inactive',
        'roles' => ($result->isadmin) ? 'ROLE_ADMIN,ROLE_USER' : 'ROLE_USER',
        ));

    $sql = "SELECT *, FIND_IN_SET('ROLE_ADMIN',roles) as roladmin  FROM users WHERE iduser = :id";
	$user = $app['dbs']['local']->executeQuery($sql, array("id" => $id))->fetch();
	$result->name = $user['name'];
	$result->email = $user['email'];
	$result->gender = $user['gender'];
	$result->dni = $user['dni'];
	$result->phone = $user['phone'];
	$result->movil = $user['movil'];
	$result->address = $user['address'];
	$result->active = ($user['status'] == 'active');
	$result->isadmin = ($user['roladmin'] > 0);
	$result->active = $user['status'] == 'active';
	$result->id = $id;
    $result->success = "Su perfil ha sido actualizado con éxito.";
    
    return $app['twig']->render('/admin/edit.html.twig',  (array) $result);
});

$controller->get('/avatar/{id}', function (Request $request, $id) use($app) {
	//get user
	$admin = $app['security.token_storage']->getToken()->getUser();
    $sql = "SELECT iduser FROM users WHERE email = :email AND FIND_IN_SET('ROLE_ADMIN',roles)>0 ";
	$admin = $app['dbs']['local']->executeQuery($sql, array("email" => $admin->getUsername()))->fetch();
	if(!$admin){
	    $app->abort(401, "Usuario no autorizado." );
	}
	
	if(!$id){
	    $app->abort(400, "Usuario no encontrado." );
	}
	
    $sql = "SELECT avatar FROM users WHERE iduser = :id";
	$usuario = $app['dbs']['local']->executeQuery($sql, array("id" => $id))->fetch();
	if(empty($usuario['avatar'])){
	    return $app->sendFile($app['resources_folder'].'/images/default-picture.png');
	}

    $response = new \Symfony\Component\HttpFoundation\Response($usuario['avatar'], 200, array(
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => sizeof($usuario['avatar']),
        'Content-Disposition' => 'attachment; filename="avatar.jpg"',
    ));
    
    return $response;
});

$app->mount('/admin', $controller);