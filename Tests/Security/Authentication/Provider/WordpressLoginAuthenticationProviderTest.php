<?php

namespace Hypebeast\WordpressBundle\Tests\Security\Authentication\Provider;

use Hypebeast\WordpressBundle\Security\Authentication\Provider\WordpressLoginAuthenticationProvider;
use Hypebeast\WordpressBundle\Security\User\WordpressUser;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test class for WordpressLoginAuthenticationProvider.
 * Generated by PHPUnit on 2011-09-29 at 14:41:47.
 * 
 * @covers Hypebeast\WordpressBundle\Security\Authentication\Provider\WordpressLoginAuthenticationProvider
 */
class WordpressLoginAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var WordpressLoginAuthenticationProvider
     */
    protected $object;

    /**
     *
     * @var Hypebeast\WordpressBundle\Wordpress\ApiAbstraction
     */
    protected $api;

    /*
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */

    protected function setUp()
    {
        $this->api = $this->getMockBuilder('Hypebeast\\WordpressBundle\\Wordpress\\ApiAbstraction')
                        ->disableOriginalConstructor()
                        ->setMethods(array('wp_signon', 'get_user_by'))
                        ->getMock();

        $this->object = new WordpressLoginAuthenticationProvider($this->api);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        
    }

    public function testAuthenticateLogsUserIntoWordpress()
    {
        $user = $this->getMock('\WP_User');
        $user->ID = 99;
        $user->user_login = $username = 'user';
        $user->roles = array('somerole', 'anotherrole');

        $this->api->expects($this->once())->method('wp_signon')
                ->with(array(
                    'user_login' => $username,
                    'user_password' => $password = 'password',
                    'remember' => false
                ))->will($this->returnValue($user));

        $result = $this->object->authenticate(
                new UsernamePasswordToken($username, $password, $key = 'key'));

        # We should get back an equivalent authenticated UsernamePasswordToken
        $this->assertInstanceOf(
                'Symfony\\Component\\Security\\Core\\Authentication\\Token\\UsernamePasswordToken',
                $result
        );
        $this->assertTrue($result->isAuthenticated());
        $this->assertEquals($username, $result->getUsername());
        $this->assertEquals($password, $result->getCredentials());
        $this->assertEquals($key, $result->getProviderKey());
        $this->assertEquals(
                array(new Role('ROLE_WP_SOMEROLE'), new Role('ROLE_WP_ANOTHERROLE')),
                $result->getRoles()
        );
    }
    
    public function testAuthenticateWithRememberMeUsesWordpressRememberMe()
    {
        $request = new Request(array(), array('remember me' => '1'));
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $container->expects($this->any())->method('get')->with('request')
                ->will($this->returnValue($request));
        
        $user = $this->getMock('\WP_User');
        $user->user_login = '';
        $user->roles = array();

        $this->api->expects($this->once())->method('wp_signon')
                ->with(array('user_login' => 'user', 'user_password' => 'pass', 'remember' => true))
                ->will($this->returnValue($user));
        
        $provider = new WordpressLoginAuthenticationProvider($this->api, 'remember me', $container);
        $provider->authenticate(new UsernamePasswordToken('user', 'pass', 'key'));
    }

    public function testAuthenticateWithCurrentUserReturnsToken()
    {
        # Return a mock user from the username lookup
        $wpUser = $this->getMock('\WP_User');
        $wpUser->ID = 99;
        $wpUser->user_login = $username = 'frankenfurter';
        $wpUser->roles = array('somerole', 'anotherrole');

        $this->api->expects($this->any())->method('get_user_by')->with('login', $username)
                ->will($this->returnValue($wpUser));
        $this->api->expects($this->never())->method('wp_signon');

        $user = $this->getMockBuilder('Hypebeast\\WordpressBundle\\Security\\User\\WordpressUser')
                ->disableOriginalConstructor()->setMethods(array('none'))->getMock();
        $user->user_login = $username;

        $result = $this->object->authenticate(new UsernamePasswordToken($user, null, 'key'));

        # We should get back an equivalent authenticated UsernamePasswordToken
        $this->assertInstanceOf(
                'Symfony\\Component\\Security\\Core\\Authentication\\Token\\UsernamePasswordToken',
                $result
        );
        $this->assertTrue($result->isAuthenticated());
        $this->assertEquals(new WordpressUser($wpUser), $result->getUser());
        $this->assertEquals(
                array(new Role('ROLE_WP_SOMEROLE'), new Role('ROLE_WP_ANOTHERROLE')),
                $result->getRoles()
        );
    }
    
    public function testAuthenticateThrowsExceptionOnFailure()
    {
        $error = $this->getMockBuilder('\WP_Error')->setMethods(array('get_error_messages'))
                ->getMock();
        $error->expects($this->any())->method('get_error_messages')
                ->will($this->returnValue(
                    $errorMessages = array('first message', 'second message')));

        $this->api->expects($this->any())->method('wp_signon')->will($this->returnValue($error));
        
        $this->setExpectedException(
                'Symfony\\Component\\Security\\Core\\Exception\\AuthenticationException',
                implode(', ', $errorMessages)
        );
        
        $this->object->authenticate(new UsernamePasswordToken('u', 'p', 'k'));
    }
    
    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AuthenticationServiceException
     */
    public function testAuthenticationThrowsExceptionOnInvalidApiResponse()
    {
        $this->api->expects($this->any())->method('wp_signon')->will($this->returnValue(null));
        $this->object->authenticate(new UsernamePasswordToken('u', 'p', 'k'));
    }

    public function testSupports()
    {
        $this->assertTrue(
                $this->object->supports(new UsernamePasswordToken('user', 'pass', 'key')));

        $this->assertFalse($this->object->supports($this->getMockForAbstractClass(
                'Symfony\\Component\\Security\\Core\\Authentication\\Token\\AbstractToken')));
    }

}

?>
