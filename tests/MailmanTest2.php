<?php
declare(strict_types=1);

namespace Adrx\MailmanService\Tests;


use Adrx\MailmanService\Mailman;
use Adrx\MailmanService\MailmanServiceException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MailmanTest2 extends TestCase
{
    /** @var History */
    private $history;

    protected function getMockMailman($responses, $baseUri = 'http://example.com/mailman')
    {
        $client = new MockHttpClient($responses);
        $browser = new HttpBrowser($client);
        $this->history = $browser->getHistory();
        $lists = ['test' => 'password'];

        return new Mailman($baseUri, $lists, $browser);
    }

    /**
     * @expectedException MailmanServiceException
     */
    public function testChangeAlreadyMember()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/change.200alreadymember.html');
        $response = new MockResponse($html);
        $mailman = $this->getMockMailman([$response]);
        $mailman->change('test', 'test@test.com', 'test2@test.com');
    }

    /**
     * @expectedException MailmanServiceException
     */
    public function testChangeNotAMember()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/change.200notamember.html');
        $response = new MockResponse($html);
        $mailman = $this->getMockMailman([$response]);
        $mailman->change('test', 'test@test.com', 'test2@test.com');
    }

    public function testChangeSuccess():void
    {
        $html = file_get_contents(dirname(__FILE__).'/html/change.200success.html');
        $response = new MockResponse($html);
        $mailman = $this->getMockMailman([$response]);
        $test = $mailman->change('test', 'test@test.com', 'test2@test.com');
        $this->assertTrue($test);
    }

    public function testRosterSuccess()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/admin-login.html');
        $responses[] = new MockResponse($html);
        $cookie = (string) new Cookie('test+admin', '123456', null, null, 'example.com');
        $info['response_headers'] = ['Set-Cookie' => $cookie];
        $responses[] = new MockResponse('', $info);
        $html = file_get_contents(dirname(__FILE__).'/html/roster.200success.html');
        $responses[] = new MockResponse($html);
        $mailman = $this->getMockMailman($responses);
        $list = $mailman->roster('test');
        $cookies = $this->history->current()->getCookies();
        $this->assertInternalType('array', $list);
        $this->assertArraySubset(['test2@subnets.org'], $list);
        $this->assertSame('123456', $cookies['test+admin']);
    }

    public function testHttpBrowserCookieHandling()
    {
        $cookie = (string) new Cookie('test+admin', '123456', null, null, 'example.com');
        $info['response_headers'] = ['Set-Cookie' => $cookie];
        $responses[] = new MockResponse('', $info);
        $responses[] = new MockResponse('');
        $client = new MockHttpClient($responses);
        $httpBrowser = new HttpBrowser($client);
        $httpBrowser->request('POST', 'http://example.com/mailman/admin/test',
            ['body' => ['adminpw' => 'pasword', 'admlogin' => 'Let me in...']]);
        $h = $httpBrowser->getResponse()->getHeaders();
        $this->assertTrue(array_key_exists('set-cookie', $h));
        $this->assertSame("test+admin=123456; domain=example.com; path=/; httponly", $h['set-cookie'][0]);
        $httpBrowser->request('GET', 'http://example.com/mailman/roster/test');
        $c = $httpBrowser->getRequest()->getCookies();
        $this->assertTrue(array_key_exists('test+admin', $c));
        $this->assertSame('123456', $c['test+admin']);
    }
}
