<?php
declare(strict_types=1);


namespace Adrx\MailmanService\Tests;


use Adrx\MailmanService\Mailman;
use Adrx\MailmanService\MailmanServiceException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MailmanTest extends TestCase
{
    /** @var History */
    private $history;

    protected function getMockMailman($responses, $baseUri = 'http://example.co.uk/mailman')
    {
        $client = new MockHttpClient($responses, $baseUri);
        $browser = new HttpBrowser($client);
        $this->history = $browser->getHistory();
        $lists = ['test_example.co.uk' => 'password'];

        return new Mailman($baseUri, $lists, $browser);
    }

    public function testSubscribe()
    {
        $html_success = file_get_contents(dirname(__FILE__).'/html/members-add-success.html');
        $html_fail = file_get_contents(dirname(__FILE__).'/html/members-add-fail.html');
        $responses = [new MockResponse($html_success), new MockResponse($html_fail)];
        $mailman = $this->getMockMailman($responses);
        try {
            $mailman->subscribe('test_example.co.uk', 'a@example.net');
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        try {
            $mailman->subscribe('test_example.co.uk', 'a@example.net');
        } catch (MailmanServiceException $e) {
            $this->assertEquals('Error subscribing: a@example.net -- Already a member', $e->getMessage());
        }
    }

    public function testUnsubscribe()
    {
        $html_success = file_get_contents(dirname(__FILE__).'/html/members-remove-success.html');
        $html_fail = file_get_contents(dirname(__FILE__).'/html/members-remove-fail.html');
        $responses = [new MockResponse($html_success), new MockResponse($html_fail)];
        $mailman = $this->getMockMailman($responses);
        try {
            $mailman->unsubscribe('test_example.co.uk','a@example.net');
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        // fail
        try {
            $mailman->unsubscribe('test_example.co.uk','a@example.net');
        } catch (MailmanServiceException $e) {
            $this->assertEquals('Cannot unsubscribe non-members', $e->getMessage());
        }
    }

    public function testMember()
    {
        $html_success = file_get_contents(dirname(__FILE__).'/html/findmember-james.html');
        $html_fail = file_get_contents(dirname(__FILE__).'/html/findmember-fail.html');
        $responses = [
            new MockResponse($html_success),
            new MockResponse($html_fail),
        ];
        $mailman = $this->getMockMailman($responses);
        // success
        $expected = [
            [
            'address' => "james.smith@example.co.uk",
            'realname' => "",
            'mod' => "off",
            'hide' => "off",
            'nomail' => "off",
            'ack' => "off",
            'notmetoo' => "off",
            'nodupes' => "on",
            'digest' => "off",
            'plain' => "on",
            'language' => "en",
            ],
            [
                'address' => "james.jones@example.co.uk",
                'realname' => "",
                'mod' => "off",
                'hide' => "off",
                'nomail' => "off",
                'ack' => "off",
                'notmetoo' => "off",
                'nodupes' => "off",
                'digest' => "on",
                'plain' => "off",
                'language' => "en",
            ],
        ];
        $member = $mailman->member('test_example.co.uk','james');
        $this->assertEquals($expected, $member);

        // fail
        try {
            $mailman->member('test_example.co.uk','fail');
        } catch (MailmanServiceException $e) {
            $this->assertEquals('No match',  $e->getMessage());
        }
    }

    public function testModAll()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/members-big.html');
        // test on
        $responses = [new MockResponse($html), new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $mailman->modAll('test_example.co.uk', true);
        $lastRequest = $this->history->current();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $parameters = $this->history->current()->getParameters();
        $this->assertTrue(isset($parameters['allmodbit_btn']));
        $this->assertTrue(isset($parameters['allmodbit_val']));
        $this->assertEquals(1, $parameters['allmodbit_val']);
        // test off
        $responses = [new MockResponse($html), new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $mailman->modAll('test_example.co.uk', false);
        $lastRequest = $this->history->current();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $parameters = $this->history->current()->getParameters();
        $this->assertTrue(isset($parameters['allmodbit_btn']));
        $this->assertTrue(isset($parameters['allmodbit_val']));
        $this->assertEquals(0, $parameters['allmodbit_val']);
    }

    public function testModSubscriberBig()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/members-big.html');
        // test on
        $responses = [new MockResponse($html), new MockResponse($html), new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $mailman->modSubscriber('test_example.co.uk','a2000@example.com', true);
        $lastRequest = $this->history->current();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $parameters = $this->history->current()->getParameters();
        $parameter = urlencode('a2000@example.com').'_mod';
        $this->assertTrue(isset($parameters[$parameter]));

    }

    public function testModSubscriberShort()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/members-short.html');
        // test on
        $responses = [new MockResponse($html), new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $mailman->modSubscriber('test_example.co.uk', 'test@example.com', true);
        $lastRequest = $this->history->current();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $parameters = $this->history->current()->getParameters();
        $parameter = urlencode('test@example.com').'_mod';
        $this->assertTrue(isset($parameters[$parameter]));
        // test off
        $responses = [new MockResponse($html), new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $mailman->modSubscriber('test_example.co.uk', 'test@example.com', false);
        $lastRequest = $this->history->current();
        $this->assertEquals('POST', $lastRequest->getMethod());
        $parameters = $this->history->current()->getParameters();
        $parameter = urlencode('test@example.com').'_mod';
        $this->assertFalse(isset($parameters[$parameter]));
    }


    /**
     * @dataProvider subscriberDataProvider
     */
    public function testIsSubscribed($email, $expected)
    {
        $html = file_get_contents(dirname(__FILE__).'/html/findmember-james.html');
        $responses[] = new MockResponse($html);
        $mailman = $this->getMockMailman($responses);

        $this->assertSame($expected, $mailman->isSubscribed('test_example.co.uk', $email));
    }

    public function subscriberDataProvider()
    {
        return [
            ['james.smith@example.co.uk', true],
            ['james.jones@example.co.uk', true],
            ['James.Smith@example.co.uk', true],
            ['James.Jones@example.co.uk', true],
            ['flange@example.co.uk', false],
            ['flange', false],
        ];
    }

    public function testLists()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/mail.cpanel.net.html');
        $responses = [new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $lists = $mailman->lists();
        $expected = [
            0 => [
                0 => "edge-users_cpanel.net",
                1 => "Edge-Users",
                2 => "Edge-Users",
                "path"=> "edge-users_cpanel.net",
                "name" => "Edge-Users",
                "desc" => "Edge-Users",
            ],
            1 => [
                0 => "integration-announce_cpanel.net",
                1 => "Integration-announce",
                2 => "cPanel Integration Announcements",
                "path" => "integration-announce_cpanel.net",
                "name" => "Integration-announce",
                "desc" => "cPanel Integration Announcements",
            ],
            2 => [
                0 => "news_cpanel.net",
                1 => "News",
                2 => "cPanel News",
                "path" => "news_cpanel.net",
                "name" => "News",
                "desc" => "cPanel News",
            ],
            3 => [
                0 => "newtech_cpanel.net",
                1 => "Newtech",
                2 => "Discussion of User Interface new technology preview releases.",
                "path" => "newtech_cpanel.net",
                "name" => "Newtech",
                "desc" => "Discussion of User Interface new technology preview releases.",
            ],
            4 => [
                0 => "releases_cpanel.net",
                1 => "Releases",
                2 => "cPanel Release Information",
                "path" => "releases_cpanel.net",
                "name" => "Releases",
                "desc" => "cPanel Release Information",
            ]
        ];
        $this->assertEquals($expected, $lists);
    }

//    public function testSetOption()
//    {
//
//    }
//
    public function testMembersBig()
    {
        $html = file_get_contents(dirname(__FILE__).'/html/members-big.html');
        $responses = [new MockResponse($html)];
        foreach (range('a', 'z') as $letter) {
            if ('q' == $letter) {
                continue;
            }
            $body[$letter] = str_replace('a2000', $letter.'2000', $html);
            $responses[] = new MockResponse($body[$letter]);
        }
        $mailman = $this->getMockMailman($responses);
        $members = $mailman->members('test_example.co.uk');
        $expected = [
            [
                "a2000@example.com",
                "b2000@example.com",
                "c2000@example.com",
                "d2000@example.com",
                "e2000@example.com",
                "f2000@example.com",
                "g2000@example.com",
                "h2000@example.com",
                "i2000@example.com",
                "j2000@example.com",
                "k2000@example.com",
                "l2000@example.com",
                "m2000@example.com",
                "n2000@example.com",
                "o2000@example.com",
                "p2000@example.com",
                "r2000@example.com",
                "s2000@example.com",
                "t2000@example.com",
                "u2000@example.com",
                "v2000@example.com",
                "w2000@example.com",
                "x2000@example.com",
                "y2000@example.com",
                "z2000@example.com",
            ],
            [
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
            ]
        ];
        $this->assertEquals($expected, $members);
    }
    public function testMembersEmpty()
    {
        $html=file_get_contents(dirname(__FILE__).'/html/members-empty.html');
        $responses = [new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $members=$mailman->members('test_example.co.uk');
        $expected = [[], []];
        $this->assertEquals($expected, $members);
    }
    public function testMembersShort()
    {
        $html=file_get_contents(dirname(__FILE__).'/html/members-short.html');
        $responses = [new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);
        $members=$mailman->members('test_example.co.uk');
        $expected = [['test@example.com'], ['']];
        $this->assertEquals($expected, $members);
    }
//
    public function testSetDigest()
    {
        $html_success = file_get_contents(dirname(__FILE__).'/html/setdigest-success.html');
        $html_fail = file_get_contents(dirname(__FILE__).'/html/setdigest-fail.html');
        $responses = [new MockResponse($html_success), new MockResponse($html_fail)];
        $mailman = $this->getMockMailman($responses);

        // success
        $this->assertEquals('1', $mailman->setDigest('test_example.co.uk', 'john.smith@example.co.uk','1'));
        // fail
        try {
            $mailman->setDigest('test_example.co.uk', 'fail@example.co.uk','1');
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    public function testVersion()
    {
        $html=file_get_contents(dirname(__FILE__).'/html/mail.cpanel.net.html');
        $responses = [new MockResponse($html)];
        $mailman = $this->getMockMailman($responses);

        $version = $mailman->version('test_example.co.uk');
        $this->assertEquals('2.1.20', $version);
    }
}
