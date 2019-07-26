<?php


namespace Adrx\MailmanService\Tests;


use Adrx\MailmanService\Mailman;
use Adrx\MailmanService\MailmanServiceException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MailmanTest extends TestCase
{
    /** @var Mailman */
    protected $Mailman;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        parent::setUp();
        $testURL = 'http://example.co.uk/mailman/admin';
        $testList = 'test_example.co.uk';
        $testPW = 'password';
        $this->Mailman = new Mailman($testURL, $testList, $testPW);

    }
    protected function getMockGuzzleClient($htmls = [])
    {
        $mock = new MockHandler();
        foreach ($htmls as $html) {
            $mock->append((new Response(200, [], $html)));
        }

        return new GuzzleClient(['handler' => $mock]);
    }

    public function testSubscribe()
    {
        $html_success = file_get_contents(dirname(__FILE__) . '/members-add-success.html');
        $html_fail = file_get_contents(dirname(__FILE__) . '/members-add-fail.html');
        $guzzleClient = $this->getMockGuzzleClient([$html_success, $html_fail]);
        $this->Mailman->setGuzzleClient($guzzleClient);
        try {
            $this->Mailman->subscribe('a@example.net');
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        try {
            $this->Mailman->subscribe('a@example.net');
        } catch (MailmanServiceException $e) {
            $this->assertEquals('Error subscribing', $e->getMessage());
        }
    }

    public function testUnsubscribe()
    {
        $html_success = file_get_contents(dirname(__FILE__) . '/members-remove-success.html');
        $html_fail = file_get_contents(dirname(__FILE__) . '/members-remove-fail.html');

        $guzzleClient = $this->getMockGuzzleClient([$html_success, $html_fail]);
        $this->Mailman->setGuzzleClient($guzzleClient);
        try {
            $this->Mailman->unsubscribe('a@example.net');
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        // fail
        try {
            $this->Mailman->unsubscribe('a@example.net');
        } catch (MailmanServiceException $e) {
            $this->assertEquals('Cannot unsubscribe non-members', $e->getMessage());
        }
    }

    public function testMember()
    {
        $html_success = file_get_contents(dirname(__FILE__) . '/findmember-james.html');
        $html_fail = file_get_contents(dirname(__FILE__) . '/findmember-fail.html');
        $guzzleClient = $this->getMockGuzzleClient([$html_success, $html_fail]);
        $this->Mailman->setGuzzleClient($guzzleClient);

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
        $member = $this->Mailman->member('james');
        $this->assertEquals($expected, $member);
        // fail
        try {
            var_dump($this->Mailman->member('fail'));
        } catch (MailmanServiceException $e) {
            $this->assertEquals('No match',  $e->getMessage());
        }
    }

    public function testLists()
    {
        $html = file_get_contents(dirname(__FILE__).'/mail.cpanel.net.html');
        $guzzleClient = $this->getMockGuzzleClient([$html]);
        $this->Mailman->setGuzzleClient($guzzleClient);
        $lists = $this->Mailman->lists();
//        var_dump($lists);
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
        $html = file_get_contents(dirname(__FILE__).'/members-big.html');
        $htmls[] = $html;
        foreach (range('a', 'z') as $letter) {
            $htmls[] = str_replace('a2000', $letter.'2000', $html);
        }
        $guzzleClient = $this->getMockGuzzleClient($htmls);
        $this->Mailman->setGuzzleClient($guzzleClient);
        $members = $this->Mailman->members();
//        var_dump($members);
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
                "q2000@example.com",
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
                "",
            ]
        ];
        $this->assertEquals($expected, $members);
    }
    public function testMembersEmpty()
    {
        $html=file_get_contents(dirname(__FILE__) . '/members-empty.html');
        $guzzleClient = $this->getMockGuzzleClient([$html]);
        $this->Mailman->setGuzzleClient($guzzleClient);
        $members=$this->Mailman->members();
//        var_dump($members);
        $expected = [[], []];
        $this->assertEquals($expected, $members);
    }
    public function testMembersShort()
    {
        $html=file_get_contents(dirname(__FILE__) . '/members-short.html');
        $guzzleClient = $this->getMockGuzzleClient([$html]);
        $this->Mailman->setGuzzleClient($guzzleClient);
        $members=$this->Mailman->members();
//        var_dump($members);
        $expected = [['test@example.com'], ['']];
        $this->assertEquals($expected, $members);
    }
//
    public function testSetDigest()
    {
        $html_success = file_get_contents(dirname(__FILE__) . '/setdigest-success.html');
        $html_fail = file_get_contents(dirname(__FILE__) . '/setdigest-fail.html');
        $guzzleClient = $this->getMockGuzzleClient([$html_success, $html_fail]);
        $this->Mailman->setGuzzleClient($guzzleClient);

        // success
        $this->assertEquals('1', $this->Mailman->setDigest('john.smith@example.co.uk',1));
        // fail
        try {
            $this->Mailman->setDigest('fail@example.co.uk',1);
        } catch (MailmanServiceException $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
    }

    public function testVersion()
    {
        $html=file_get_contents(dirname(__FILE__) . '/mail.cpanel.net.html');
        $guzzleClient = $this->getMockGuzzleClient([$html]);
        $this->Mailman->setGuzzleClient($guzzleClient);

        $version = $this->Mailman->version();
        echo $version;
        $this->assertEquals('2.1.20', $version);
    }
}
