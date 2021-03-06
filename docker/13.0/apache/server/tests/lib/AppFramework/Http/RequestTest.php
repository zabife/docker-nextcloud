<?php
/**
 * @copyright 2013 Thomas Tanghus (thomas@tanghus.net)
 * @copyright 2016 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\AppFramework\Http;

use OC\AppFramework\Http\Request;
use OC\Security\CSRF\CsrfToken;
use OC\Security\CSRF\CsrfTokenManager;
use OCP\Security\ISecureRandom;
use OCP\IConfig;

/**
 * Class RequestTest
 *
 * @package OC\AppFramework\Http
 */
class RequestTest extends \Test\TestCase {
	/** @var string */
	protected $stream = 'fakeinput://data';
	/** @var ISecureRandom */
	protected $secureRandom;
	/** @var IConfig */
	protected $config;
	/** @var CsrfTokenManager */
	protected $csrfTokenManager;

	protected function setUp() {
		parent::setUp();

		if (in_array('fakeinput', stream_get_wrappers())) {
			stream_wrapper_unregister('fakeinput');
		}
		stream_wrapper_register('fakeinput', 'Test\AppFramework\Http\RequestStream');

		$this->secureRandom = $this->getMockBuilder('\OCP\Security\ISecureRandom')->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->csrfTokenManager = $this->getMockBuilder('\OC\Security\CSRF\CsrfTokenManager')
			->disableOriginalConstructor()->getMock();
	}

	protected function tearDown() {
		stream_wrapper_unregister('fakeinput');
		parent::tearDown();
	}

	public function testRequestAccessors() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'method' => 'GET',
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		// Countable
		$this->assertSame(2, count($request));
		// Array access
		$this->assertSame('Joey', $request['nickname']);
		// "Magic" accessors
		$this->assertSame('Joey', $request->{'nickname'});
		$this->assertTrue(isset($request['nickname']));
		$this->assertTrue(isset($request->{'nickname'}));
		$this->assertFalse(isset($request->{'flickname'}));
		// Only testing 'get', but same approach for post, files etc.
		$this->assertSame('Joey', $request->get['nickname']);
		// Always returns null if variable not set.
		$this->assertSame(null, $request->{'flickname'});

	}

	// urlParams has precedence over POST which has precedence over GET
	public function testPrecedence() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'post' => array('name' => 'Jane Doe', 'nickname' => 'Janey'),
			'urlParams' => array('user' => 'jw', 'name' => 'Johnny Weissm??ller'),
			'method' => 'GET'
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame(3, count($request));
		$this->assertSame('Janey', $request->{'nickname'});
		$this->assertSame('Johnny Weissm??ller', $request->{'name'});
	}


	/**
	 * @expectedException \RuntimeException
	 */
	public function testImmutableArrayAccess() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'method' => 'GET'
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$request['nickname'] = 'Janey';
	}

	/**
	 * @expectedException \RuntimeException
	 */
	public function testImmutableMagicAccess() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'method' => 'GET'
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$request->{'nickname'} = 'Janey';
	}

	/**
	 * @expectedException \LogicException
	 */
	public function testGetTheMethodRight() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'method' => 'GET',
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$request->post;
	}

	public function testTheMethodIsRight() {
		$vars = array(
			'get' => array('name' => 'John Q. Public', 'nickname' => 'Joey'),
			'method' => 'GET',
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('GET', $request->method);
		$result = $request->get;
		$this->assertSame('John Q. Public', $result['name']);
		$this->assertSame('Joey', $result['nickname']);
	}

	public function testJsonPost() {
		global $data;
		$data = '{"name": "John Q. Public", "nickname": "Joey"}';
		$vars = array(
			'method' => 'POST',
			'server' => array('CONTENT_TYPE' => 'application/json; utf-8')
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('POST', $request->method);
		$result = $request->post;
		$this->assertSame('John Q. Public', $result['name']);
		$this->assertSame('Joey', $result['nickname']);
		$this->assertSame('Joey', $request->params['nickname']);
		$this->assertSame('Joey', $request['nickname']);
	}

	public function testNotJsonPost() {
		global $data;
		$data = 'this is not valid json';
		$vars = array(
			'method' => 'POST',
			'server' => array('CONTENT_TYPE' => 'application/json; utf-8')
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertEquals('POST', $request->method);
		$result = $request->post;
		// ensure there's no error attempting to decode the content
	}

	public function testPatch() {
		global $data;
		$data = http_build_query(array('name' => 'John Q. Public', 'nickname' => 'Joey'), '', '&');

		$vars = array(
			'method' => 'PATCH',
			'server' => array('CONTENT_TYPE' => 'application/x-www-form-urlencoded'),
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('PATCH', $request->method);
		$result = $request->patch;

		$this->assertSame('John Q. Public', $result['name']);
		$this->assertSame('Joey', $result['nickname']);
	}

	public function testJsonPatchAndPut() {
		global $data;

		// PUT content
		$data = '{"name": "John Q. Public", "nickname": "Joey"}';
		$vars = array(
			'method' => 'PUT',
			'server' => array('CONTENT_TYPE' => 'application/json; utf-8'),
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('PUT', $request->method);
		$result = $request->put;

		$this->assertSame('John Q. Public', $result['name']);
		$this->assertSame('Joey', $result['nickname']);

		// PATCH content
		$data = '{"name": "John Q. Public", "nickname": null}';
		$vars = array(
			'method' => 'PATCH',
			'server' => array('CONTENT_TYPE' => 'application/json; utf-8'),
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('PATCH', $request->method);
		$result = $request->patch;

		$this->assertSame('John Q. Public', $result['name']);
		$this->assertSame(null, $result['nickname']);
	}

	public function testPutStream() {
		global $data;
		$data = file_get_contents(__DIR__ . '/../../../data/testimage.png');

		$vars = array(
			'put' => $data,
			'method' => 'PUT',
			'server' => [
				'CONTENT_TYPE' => 'image/png',
				'CONTENT_LENGTH' => (string)strlen($data)
			],
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('PUT', $request->method);
		$resource = $request->put;
		$contents = stream_get_contents($resource);
		$this->assertSame($data, $contents);

		try {
			$resource = $request->put;
		} catch(\LogicException $e) {
			return;
		}
		$this->fail('Expected LogicException.');

	}


	public function testSetUrlParameters() {
		$vars = array(
			'post' => array(),
			'method' => 'POST',
			'urlParams' => array('id' => '2'),
		);

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$newParams = array('id' => '3', 'test' => 'test2');
		$request->setUrlParameters($newParams);
		$this->assertSame('test2', $request->getParam('test'));
		$this->assertEquals('3', $request->getParam('id'));
		$this->assertEquals('3', $request->getParams()['id']);
	}

	public function testGetIdWithModUnique() {
		$vars = [
			'server' => [
				'UNIQUE_ID' => 'GeneratedUniqueIdByModUnique'
			],
		];

		$request = new Request(
			$vars,
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('GeneratedUniqueIdByModUnique', $request->getId());
	}

	public function testGetIdWithoutModUnique() {
		$this->secureRandom->expects($this->once())
			->method('generate')
			->with('20')
			->will($this->returnValue('GeneratedByOwnCloudItself'));

		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('GeneratedByOwnCloudItself', $request->getId());
	}

	public function testGetIdWithoutModUniqueStable() {
		$request = new Request(
			[],
			\OC::$server->getSecureRandom(),
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$firstId = $request->getId();
		$secondId = $request->getId();
		$this->assertSame($firstId, $secondId);
	}

	public function testGetRemoteAddressWithoutTrustedRemote() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('trusted_proxies')
			->will($this->returnValue([]));

		$request = new Request(
			[
				'server' => [
					'REMOTE_ADDR' => '10.0.0.2',
					'HTTP_X_FORWARDED' => '10.4.0.5, 10.4.0.4',
					'HTTP_X_FORWARDED_FOR' => '192.168.0.233'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('10.0.0.2', $request->getRemoteAddress());
	}

	public function testGetRemoteAddressWithNoTrustedHeader() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('trusted_proxies')
			->will($this->returnValue(['10.0.0.2']));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('forwarded_for_headers')
			->will($this->returnValue([]));

		$request = new Request(
			[
				'server' => [
					'REMOTE_ADDR' => '10.0.0.2',
					'HTTP_X_FORWARDED' => '10.4.0.5, 10.4.0.4',
					'HTTP_X_FORWARDED_FOR' => '192.168.0.233'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('10.0.0.2', $request->getRemoteAddress());
	}

	public function testGetRemoteAddressWithSingleTrustedRemote() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('trusted_proxies')
			->will($this->returnValue(['10.0.0.2']));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('forwarded_for_headers')
			->will($this->returnValue(['HTTP_X_FORWARDED']));

		$request = new Request(
			[
				'server' => [
					'REMOTE_ADDR' => '10.0.0.2',
					'HTTP_X_FORWARDED' => '10.4.0.5, 10.4.0.4',
					'HTTP_X_FORWARDED_FOR' => '192.168.0.233'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('10.4.0.5', $request->getRemoteAddress());
	}

	public function testGetRemoteAddressVerifyPriorityHeader() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('trusted_proxies')
			->will($this->returnValue(['10.0.0.2']));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('forwarded_for_headers')
			->will($this->returnValue([
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED'
			]));

		$request = new Request(
			[
				'server' => [
					'REMOTE_ADDR' => '10.0.0.2',
					'HTTP_X_FORWARDED' => '10.4.0.5, 10.4.0.4',
					'HTTP_X_FORWARDED_FOR' => '192.168.0.233'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('192.168.0.233', $request->getRemoteAddress());
	}

	/**
	 * @return array
	 */
	public function httpProtocolProvider() {
		return [
			// Valid HTTP 1.0
			['HTTP/1.0', 'HTTP/1.0'],
			['http/1.0', 'HTTP/1.0'],
			['HTTp/1.0', 'HTTP/1.0'],

			// Valid HTTP 1.1
			['HTTP/1.1', 'HTTP/1.1'],
			['http/1.1', 'HTTP/1.1'],
			['HTTp/1.1', 'HTTP/1.1'],

			// Valid HTTP 2.0
			['HTTP/2', 'HTTP/2'],
			['http/2', 'HTTP/2'],
			['HTTp/2', 'HTTP/2'],

			// Invalid
			['HTTp/394', 'HTTP/1.1'],
			['InvalidProvider/1.1', 'HTTP/1.1'],
			[null, 'HTTP/1.1'],
			['', 'HTTP/1.1'],

		];
	}

	/**
	 * @dataProvider httpProtocolProvider
	 *
	 * @param mixed $input
	 * @param string $expected
	 */
	public function testGetHttpProtocol($input, $expected) {
		$request = new Request(
			[
				'server' => [
					'SERVER_PROTOCOL' => $input,
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($expected, $request->getHttpProtocol());
	}

	public function testGetServerProtocolWithOverride() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue('customProtocol'));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('overwritecondaddr')
			->will($this->returnValue(''));
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue('customProtocol'));

		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('customProtocol', $request->getServerProtocol());
	}

	public function testGetServerProtocolWithProtoValid() {
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$requestHttps = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_PROTO' => 'HtTpS'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$requestHttp = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_PROTO' => 'HTTp'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);


		$this->assertSame('https', $requestHttps->getServerProtocol());
		$this->assertSame('http', $requestHttp->getServerProtocol());
	}

	public function testGetServerProtocolWithHttpsServerValueOn() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$request = new Request(
			[
				'server' => [
					'HTTPS' => 'on'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$this->assertSame('https', $request->getServerProtocol());
	}

	public function testGetServerProtocolWithHttpsServerValueOff() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$request = new Request(
			[
				'server' => [
					'HTTPS' => 'off'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$this->assertSame('http', $request->getServerProtocol());
	}

	public function testGetServerProtocolWithHttpsServerValueEmpty() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$request = new Request(
			[
				'server' => [
					'HTTPS' => ''
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$this->assertSame('http', $request->getServerProtocol());
	}

	public function testGetServerProtocolDefault() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);
		$this->assertSame('http', $request->getServerProtocol());
	}

	public function testGetServerProtocolBehindLoadBalancers() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwriteprotocol')
			->will($this->returnValue(''));

		$request = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_PROTO' => 'https,http,http'
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('https', $request->getServerProtocol());
	}

	/**
	 * @dataProvider userAgentProvider
	 * @param string $testAgent
	 * @param array $userAgent
	 * @param bool $matches
	 */
	public function testUserAgent($testAgent, $userAgent, $matches) {
		$request = new Request(
			[
				'server' => [
					'HTTP_USER_AGENT' => $testAgent,
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($matches, $request->isUserAgent($userAgent));
	}

	/**
	 * @dataProvider userAgentProvider
	 * @param string $testAgent
	 * @param array $userAgent
	 * @param bool $matches
	 */
	public function testUndefinedUserAgent($testAgent, $userAgent, $matches) {
		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertFalse($request->isUserAgent($userAgent));
	}

	/**
	 * @return array
	 */
	function userAgentProvider() {
		return [
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)',
				[
					Request::USER_AGENT_IE
				],
				true,
			],
			[
				'Mozilla/5.0 (X11; Linux i686; rv:24.0) Gecko/20100101 Firefox/24.0',
				[
					Request::USER_AGENT_IE
				],
				false,
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36',
				[
					Request::USER_AGENT_CHROME
				],
				true,
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/53.0.2785.143 Chrome/53.0.2785.143 Safari/537.36',
				[
					Request::USER_AGENT_CHROME
				],
				true,
			],
			[
				'Mozilla/5.0 (Linux; Android 4.4; Nexus 4 Build/KRT16S) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.59 Mobile Safari/537.36',
				[
					Request::USER_AGENT_ANDROID_MOBILE_CHROME
				],
				true,
			],
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)',
				[
					Request::USER_AGENT_ANDROID_MOBILE_CHROME
				],
				false,
			],
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)',
				[
					Request::USER_AGENT_IE,
					Request::USER_AGENT_ANDROID_MOBILE_CHROME,
				],
				true,
			],
			[
				'Mozilla/5.0 (Linux; Android 4.4; Nexus 4 Build/KRT16S) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.59 Mobile Safari/537.36',
				[
					Request::USER_AGENT_IE,
					Request::USER_AGENT_ANDROID_MOBILE_CHROME,
				],
				true,
			],
			[
				'Mozilla/5.0 (X11; Linux i686; rv:24.0) Gecko/20100101 Firefox/24.0',
				[
					Request::USER_AGENT_FREEBOX
				],
				false,
			],
			[
				'Mozilla/5.0',
				[
					Request::USER_AGENT_FREEBOX
				],
				true,
			],
			[
				'Fake Mozilla/5.0',
				[
					Request::USER_AGENT_FREEBOX
				],
				false,
			],
			[
				'Mozilla/5.0 (Android) ownCloud-android/2.0.0',
				[
					Request::USER_AGENT_CLIENT_ANDROID
				],
				true,
			],
			[
				'Mozilla/5.0 (Android) Nextcloud-android/2.0.0',
				[
					Request::USER_AGENT_CLIENT_ANDROID
				],
				true,
			],
		];
	}

	public function testInsecureServerHostServerNameHeader() {
		$request = new Request(
			[
				'server' => [
					'SERVER_NAME' => 'from.server.name:8080',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('from.server.name:8080',  $request->getInsecureServerHost());
	}

	public function testInsecureServerHostHttpHostHeader() {
		$request = new Request(
			[
				'server' => [
					'SERVER_NAME' => 'from.server.name:8080',
					'HTTP_HOST' => 'from.host.header:8080',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('from.host.header:8080',  $request->getInsecureServerHost());
	}

	public function testInsecureServerHostHttpFromForwardedHeaderSingle() {
		$request = new Request(
			[
				'server' => [
					'SERVER_NAME' => 'from.server.name:8080',
					'HTTP_HOST' => 'from.host.header:8080',
					'HTTP_X_FORWARDED_HOST' => 'from.forwarded.host:8080',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('from.forwarded.host:8080',  $request->getInsecureServerHost());
	}

	public function testInsecureServerHostHttpFromForwardedHeaderStacked() {
		$request = new Request(
			[
				'server' => [
					'SERVER_NAME' => 'from.server.name:8080',
					'HTTP_HOST' => 'from.host.header:8080',
					'HTTP_X_FORWARDED_HOST' => 'from.forwarded.host2:8080,another.one:9000',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('from.forwarded.host2:8080',  $request->getInsecureServerHost());
	}

	public function testGetServerHostWithOverwriteHost() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('overwritehost')
			->will($this->returnValue('my.overwritten.host'));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('overwritecondaddr')
			->will($this->returnValue(''));
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('overwritehost')
			->will($this->returnValue('my.overwritten.host'));

		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('my.overwritten.host',  $request->getServerHost());
	}

	public function testGetServerHostWithTrustedDomain() {
		$this->config
			->expects($this->at(3))
			->method('getSystemValue')
			->with('trusted_domains')
			->will($this->returnValue(['my.trusted.host']));

		$request = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_HOST' => 'my.trusted.host',
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('my.trusted.host',  $request->getServerHost());
	}

	public function testGetServerHostWithUntrustedDomain() {
		$this->config
			->expects($this->at(3))
			->method('getSystemValue')
			->with('trusted_domains')
			->will($this->returnValue(['my.trusted.host']));
		$this->config
			->expects($this->at(4))
			->method('getSystemValue')
			->with('trusted_domains')
			->will($this->returnValue(['my.trusted.host']));

		$request = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_HOST' => 'my.untrusted.host',
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('my.trusted.host',  $request->getServerHost());
	}

	public function testGetServerHostWithNoTrustedDomain() {
		$this->config
			->expects($this->at(3))
			->method('getSystemValue')
			->with('trusted_domains')
			->will($this->returnValue([]));
		$this->config
			->expects($this->at(4))
			->method('getSystemValue')
			->with('trusted_domains')
			->will($this->returnValue([]));

		$request = new Request(
			[
				'server' => [
					'HTTP_X_FORWARDED_HOST' => 'my.untrusted.host',
				],
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('',  $request->getServerHost());
	}

	public function testGetOverwriteHostDefaultNull() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwritehost')
			->will($this->returnValue(''));
		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertNull(self::invokePrivate($request, 'getOverwriteHost'));
	}

	public function testGetOverwriteHostWithOverwrite() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('overwritehost')
			->will($this->returnValue('www.owncloud.org'));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('overwritecondaddr')
			->will($this->returnValue(''));
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('overwritehost')
			->will($this->returnValue('www.owncloud.org'));

		$request = new Request(
			[],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('www.owncloud.org', self::invokePrivate($request, 'getOverwriteHost'));
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage The requested uri(/foo.php) cannot be processed by the script '/var/www/index.php')
	 */
	public function testGetPathInfoNotProcessible() {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => '/foo.php',
					'SCRIPT_NAME' => '/var/www/index.php',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$request->getPathInfo();
	}

	/**
	 * @expectedException \Exception
	 * @expectedExceptionMessage The requested uri(/foo.php) cannot be processed by the script '/var/www/index.php')
	 */
	public function testGetRawPathInfoNotProcessible() {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => '/foo.php',
					'SCRIPT_NAME' => '/var/www/index.php',
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$request->getRawPathInfo();
	}

	/**
	 * @dataProvider genericPathInfoProvider
	 * @param string $requestUri
	 * @param string $scriptName
	 * @param string $expected
	 */
	public function testGetPathInfoWithoutSetEnvGeneric($requestUri, $scriptName, $expected) {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => $requestUri,
					'SCRIPT_NAME' => $scriptName,
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($expected, $request->getPathInfo());
	}

	/**
	 * @dataProvider genericPathInfoProvider
	 * @param string $requestUri
	 * @param string $scriptName
	 * @param string $expected
	 */
	public function testGetRawPathInfoWithoutSetEnvGeneric($requestUri, $scriptName, $expected) {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => $requestUri,
					'SCRIPT_NAME' => $scriptName,
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($expected, $request->getRawPathInfo());
	}

	/**
	 * @dataProvider rawPathInfoProvider
	 * @param string $requestUri
	 * @param string $scriptName
	 * @param string $expected
	 */
	public function testGetRawPathInfoWithoutSetEnv($requestUri, $scriptName, $expected) {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => $requestUri,
					'SCRIPT_NAME' => $scriptName,
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($expected, $request->getRawPathInfo());
	}

	/**
	 * @dataProvider pathInfoProvider
	 * @param string $requestUri
	 * @param string $scriptName
	 * @param string $expected
	 */
	public function testGetPathInfoWithoutSetEnv($requestUri, $scriptName, $expected) {
		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => $requestUri,
					'SCRIPT_NAME' => $scriptName,
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame($expected, $request->getPathInfo());
	}

	/**
	 * @return array
	 */
	public function genericPathInfoProvider() {
		return [
			['/core/index.php?XDEBUG_SESSION_START=14600', '/core/index.php', ''],
			['/index.php/apps/files/', 'index.php', '/apps/files/'],
			['/index.php/apps/files/../&amp;/&?someQueryParameter=QueryParam', 'index.php', '/apps/files/../&amp;/&'],
			['/remote.php/?????????????????? / ??????????????????', 'remote.php', '/?????????????????? / ??????????????????'],
			['///removeTrailin//gSlashes///', 'remote.php', '/removeTrailin/gSlashes/'],
			['/', '/', ''],
			['', '', ''],
		];
	}

	/**
	 * @return array
	 */
	public function rawPathInfoProvider() {
		return [
			['/foo%2Fbar/subfolder', '', 'foo%2Fbar/subfolder'],
		];
	}

	/**
	 * @return array
	 */
	public function pathInfoProvider() {
		return [
			['/foo%2Fbar/subfolder', '', 'foo/bar/subfolder'],
		];
	}

	public function testGetRequestUriWithoutOverwrite() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('overwritewebroot')
			->will($this->returnValue(''));

		$request = new Request(
			[
				'server' => [
					'REQUEST_URI' => '/test.php'
				]
			],
			$this->secureRandom,
			$this->config,
			$this->csrfTokenManager,
			$this->stream
		);

		$this->assertSame('/test.php', $request->getRequestUri());
	}

	public function providesGetRequestUriWithOverwriteData() {
		return [
			['/scriptname.php/some/PathInfo', '/owncloud/', ''],
			['/scriptname.php/some/PathInfo', '/owncloud/', '123'],
		];
	}

	/**
	 * @dataProvider providesGetRequestUriWithOverwriteData
	 */
	public function testGetRequestUriWithOverwrite($expectedUri, $overwriteWebRoot, $overwriteCondAddr) {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('overwritewebroot')
			->will($this->returnValue($overwriteWebRoot));
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('overwritecondaddr')
			->will($this->returnValue($overwriteCondAddr));

		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'REQUEST_URI' => '/test.php/some/PathInfo',
						'SCRIPT_NAME' => '/test.php',
					]
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$request
			->expects($this->once())
			->method('getScriptName')
			->will($this->returnValue('/scriptname.php'));

		$this->assertSame($expectedUri, $request->getRequestUri());
	}

	public function testPassesCSRFCheckWithGet() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'get' => [
						'requesttoken' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						'nc_sameSiteCookiestrict' => 'true',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$token = new CsrfToken('AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds');
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->with($token)
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithPost() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'post' => [
						'requesttoken' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						'nc_sameSiteCookiestrict' => 'true',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$token = new CsrfToken('AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds');
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->with($token)
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithHeader() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						'nc_sameSiteCookiestrict' => 'true',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$token = new CsrfToken('AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds');
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->with($token)
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithGetAndWithoutCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'get' => [
						'requesttoken' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithPostAndWithoutCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'post' => [
						'requesttoken' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithHeaderAndWithoutCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$this->csrfTokenManager
			->expects($this->once())
			->method('isTokenValid')
			->willReturn(true);

		$this->assertTrue($request->passesCSRFCheck());
	}

	public function testFailsCSRFCheckWithHeaderAndNotAllChecksPassing() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$this->csrfTokenManager
			->expects($this->never())
			->method('isTokenValid');

		$this->assertFalse($request->passesCSRFCheck());
	}

	public function testPassesStrictCookieCheckWithAllCookiesAndStrict() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName', 'getCookieParams'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'__Host-nc_sameSiteCookiestrict' => 'true',
						'__Host-nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$request
			->expects($this->any())
			->method('getCookieParams')
			->willReturn([
				'secure' => true,
				'path' => '/',
			]);

		$this->assertTrue($request->passesStrictCookieCheck());
	}

	public function testFailsStrictCookieCheckWithAllCookiesAndMissingStrict() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName', 'getCookieParams'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'true',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$request
			->expects($this->any())
			->method('getCookieParams')
			->willReturn([
				'secure' => true,
				'path' => '/',
			]);

		$this->assertFalse($request->passesStrictCookieCheck());
	}

	public function testGetCookieParams() {
		/** @var Request $request */
		$request = $this->getMockBuilder(Request::class)
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$actual = $request->getCookieParams();
		$this->assertSame(session_get_cookie_params(), $actual);
	}

	public function testPassesStrictCookieCheckWithAllCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'true',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertTrue($request->passesStrictCookieCheck());
	}

	public function testPassesStrictCookieCheckWithRandomCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						'RandomCookie' => 'asdf',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertTrue($request->passesStrictCookieCheck());
	}

	public function testFailsStrictCookieCheckWithSessionCookie() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesStrictCookieCheck());
	}

	public function testFailsStrictCookieCheckWithRememberMeCookie() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						'nc_token' => 'asdf',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesStrictCookieCheck());
	}

	public function testFailsCSRFCheckWithPostAndWithCookies() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'post' => [
						'requesttoken' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'foo' => 'bar',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();
		$this->csrfTokenManager
			->expects($this->never())
			->method('isTokenValid');

		$this->assertFalse($request->passesCSRFCheck());
	}

	public function testFailStrictCookieCheckWithOnlyLaxCookie() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesStrictCookieCheck());
	}

	public function testFailStrictCookieCheckWithOnlyStrictCookie() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesStrictCookieCheck());
	}

	public function testPassesLaxCookieCheck() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookielax' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertTrue($request->passesLaxCookieCheck());
	}

	public function testFailsLaxCookieCheckWithOnlyStrictCookie() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'true',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesLaxCookieCheck());
	}

	public function testSkipCookieCheckForOCSRequests() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => 'AAAHGxsTCTc3BgMQESAcNR0OAR0=:MyTotalSecretShareds',
						'HTTP_OCS_APIREQUEST' => 'true',
					],
					'cookies' => [
						session_name() => 'asdf',
						'nc_sameSiteCookiestrict' => 'false',
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertTrue($request->passesStrictCookieCheck());
	}

	/**
	 * @return array
	 */
	public function invalidTokenDataProvider() {
		return [
			['InvalidSentToken'],
			['InvalidSentToken:InvalidSecret'],
			[''],
		];
	}

	/**
	 * @dataProvider invalidTokenDataProvider
	 * @param string $invalidToken
	 */
	public function testPassesCSRFCheckWithInvalidToken($invalidToken) {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[
					'server' => [
						'HTTP_REQUESTTOKEN' => $invalidToken,
					],
				],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$token = new CsrfToken($invalidToken);
		$this->csrfTokenManager
			->expects($this->any())
			->method('isTokenValid')
			->with($token)
			->willReturn(false);

		$this->assertFalse($request->passesCSRFCheck());
	}

	public function testPassesCSRFCheckWithoutTokenFail() {
		/** @var Request $request */
		$request = $this->getMockBuilder('\OC\AppFramework\Http\Request')
			->setMethods(['getScriptName'])
			->setConstructorArgs([
				[],
				$this->secureRandom,
				$this->config,
				$this->csrfTokenManager,
				$this->stream
			])
			->getMock();

		$this->assertFalse($request->passesCSRFCheck());
	}
}
