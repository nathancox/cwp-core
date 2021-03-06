<?php

namespace CWP\Core\Tests\Control;

use CWP\Core\Control\InitialisationMiddleware;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\FunctionalTest;

class InitialisationMiddlewareTest extends FunctionalTest
{
    /**
     * @var HTTPRequest
     */
    protected $request;

    /**
     * @var InitialisationMiddleware
     */
    protected $middleware;

    protected $usesDatabase = true;

    protected function setUp()
    {
        parent::setUp();

        $this->request = new HTTPRequest('GET', '/');
        $this->middleware = new InitialisationMiddleware();

        Environment::setEnv('SS_OUTBOUND_PROXY', '');
        Environment::setEnv('SS_OUTBOUND_PROXY_PORT', '');
        Environment::setEnv('NO_PROXY', '');
    }

    public function testDoNotConfigureProxyIfNoEnvironmentVarsAreSet()
    {
        $this->runMiddleware();

        $this->assertEmpty(
            Environment::getEnv('http_proxy'),
            'Proxy information is not set if no outbound proxy is configured'
        );
    }

    public function testConfigureEgressProxyWhenVarsAreSet()
    {
        Environment::setEnv('SS_OUTBOUND_PROXY', 'http://example.com');
        Environment::setEnv('SS_OUTBOUND_PROXY_PORT', '8024');

        $this->runMiddleware();

        $this->assertEquals(
            'http://example.com:8024',
            Environment::getEnv('http_proxy'),
            'Proxy is configured with proxy and port'
        );
    }

    public function testDoNotConfigureProxyDomainExclusionsWhenNoneAreDefined()
    {
        Config::modify()->remove(InitialisationMiddleware::class, 'egress_proxy_exclude_domains');

        $this->runMiddleware();

        $this->assertSame(
            '',
            Environment::getEnv('NO_PROXY'),
            'No domain exclusions are set when none are defined'
        );
    }

    public function testConfigureEgressProxyDomainExclusions()
    {
        Config::modify()->set(
            InitialisationMiddleware::class,
            'egress_proxy_exclude_domains',
            'example.com'
        );

        Environment::setEnv('NO_PROXY', 'foo.com,bar.com');

        $this->runMiddleware();

        $this->assertSame(
            'foo.com,bar.com,example.com',
            Environment::getEnv('NO_PROXY'),
            'Domain exclusions are combined with existing values and configuration settings'
        );
    }

    public function testSecurityHeadersAddedByDefault()
    {
        $response = $this->get('Security/login');
        $this->assertArrayHasKey('x-xss-protection', $response->getHeaders());
        $this->assertSame('1; mode=block', $response->getHeader('x-xss-protection'));
    }

    public function testXSSProtectionHeaderNotAdded()
    {
        Config::modify()->set(InitialisationMiddleware::class, 'xss_protection_enabled', false);
        $response = $this->get('Security/login');
        $this->assertArrayNotHasKey('x-xss-protection', $response->getHeaders());
    }

    /**
     * Runs the middleware with a stubbed delegate
     */
    protected function runMiddleware()
    {
        $this->middleware->process($this->request, function () {
            // no op
        });
    }
}
