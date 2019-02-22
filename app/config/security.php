<?php

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Doctrine\DBAL\Connection;

class UserProvider implements UserProviderInterface
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function loadUserByUsername($email)
    {
        if ( $email==NULL ) {
        	throw new UnsupportedUserException(sprintf('Invalid or empty "%s" email.', $email));
        }
        
        $sql = "SELECT email, password, roles FROM users WHERE status = 'active' AND email = ?";
        $user = $this->db->executeQuery($sql, array(strtolower($email)))->fetch();
        if($user==NULL) {
        	throw new UsernameNotFoundException(sprintf('User "%s" not found.', $email ));
        }

        return new User($user['email'], $user['password'], explode(',', $user['roles']), true, true, true, true);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}

class FailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(Silex\Application &$app) {
        $this->app = $app;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new Response($this->app['twig']->render('/user/login.html.twig', array(
            'message' => $exception->getMessage(),
            'username' => $this->app['session']->get('_security.last_username'),
        )));
    }
}   

class SuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(Silex\Application &$app) {
        $this->app = $app;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return $this->app->redirect('/dashboard');
    }
}

//register urlgenerator
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

//register session service
$app->register(new Silex\Provider\SessionServiceProvider());

//register security service
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.encoder.digest' => $app->share(function ($app) { 
        return new MessageDigestPasswordEncoder('sha256', false, 1); 
    }),
    'security.authentication.failure_handler.secure' => $app->share(function ($app) {
        return new FailureHandler($app);
    }),
    'security.authentication.success_handler.secure' => $app->share(function ($app) {
        return new SuccessHandler($app);
    }),
    'security.firewalls' => array(
        'public-user'=> array(
            'anonymous' => true,
            'pattern' => '^/user/(login|register|forgot|activate/.*)$',
        ),
        'public'=> array(
            'anonymous' => true,
            'pattern' => '^/$',
        ),
        'secure' => array(
            'anonymous' => false,
            'pattern' => '^/.*',
            'form' => array(
                'login_path' => '/user/login',
                'check_path' => '/let/me/in',
                'default_target_path' => '/',
                'remember_me' => true,
                'require_previous_session' => true,
                'always_use_default_target_path' => true,
                'username_parameter' => '_email',
                'password_parameter' => '_password',
            ),
            'logout' => array(
                'logout_path' => '/user/logout'
            ),
            'users' => $app->share(function ($app) { 
                return new UserProvider($app['dbs']['local']);
            }),
        ),
    ),
    /*
    'security.access_control' => array(
        array(
            'path' => '^/user/(login|register|forgot|activate/.*)$',
            'role' => 'IS_AUTHENTICATED_ANONYMOUSLY'
        ),
        array(
            'path' => '^/$',
            'role' => 'IS_AUTHENTICATED_ANONYMOUSLY'
        ),
        array(
            'path' => '^/*.$',
            'role' => 'ROLE_USER'
        ),
    ),
    */
));