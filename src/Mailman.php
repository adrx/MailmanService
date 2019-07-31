<?php
declare(strict_types=1);

/**
 * derived from:
 *
 * Services Mailman
 *
 * Allows the integration of Mailman into a dynamic website without using
 *      Python or requiring permission to Mailman binaries
 *
 * PHP version 5
 *
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * + Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 * + Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation and/or
 * other materials provided with the distribution.
 * + Neither the name of the <ORGANIZATION> nor the names of its contributors
 * may be used to endorse or promote products derived
 * from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Services
 * @package   Services_Mailman
 * @author    James Wade
 * @copyright 2011 James Wade
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   GIT: $Id:$
 * @link      http://php-mailman.sourceforge.net/
 *
 * PLUS some code ideas from https://github.com/ghanover/mailman-sync
 */
namespace Adrx\MailmanService;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * Mailman Class
 */
class Mailman
{
    /**
     * @var array
     * associative array of $listname => $adminpw
     */
    private $lists;

    /** @var HttpBrowser */
    private $httpBrowser;

    /** @var string */
    private $baseUri;

    /**
     * Constructor
     * @param string $baseUri
     * @param array $lists associative array of $listname => $adminpw
     */
    public function __construct(string $baseUri, array $lists = [], ?HttpBrowser $httpBrowser = null)
    {
        $this->baseUri = $baseUri;
        $this->lists = $lists;
        $this->httpBrowser = $httpBrowser ?: new HttpBrowser();
    }

    /**
     * List lists
     *
     * (ie: <domain.com>/mailman/admin)
     *
     * @param boolean $assoc Associated array (default) or not
     *
     * @return array   Return an array of lists
     *
     * @throws MailmanServiceException
     */
    public function lists(bool $assoc = true): array
    {
        $path = '/admin';
        $crawler = $this->getCrawler($path);
        $paths = $crawler->filterXPath('//body/table[1]/tr/td[1]/a/@href');
        $names = $crawler->filterXPath('//body/table[1]/tr/td[1]/a/strong');
        $descs = $crawler->filterXPath('//body/table[1]/tr/td[2]');
        $count = count($names);
        if (!$count) {
            throw new MailmanServiceException(
                'Failed to parse HTML',
                MailmanServiceException::HTML_PARSE
            );
        }

        $a = array();
        for ($i=0;$i < $count;$i++) {
            if ($paths->eq($i)) {
                $a[$i][0]= $paths->eq($i) ?basename($paths->getNode($i)->nodeValue):'';
                $a[$i][1]= $names->eq($i)?$names->getNode($i)->nodeValue:'';
                $a[$i][2]= $descs->eq($i)?$descs->getNode($i+2)->textContent:'';
                if ($assoc) {
                    $a[$i]['path'] = $a[$i][0];
                    $a[$i]['name'] = $a[$i][1];
                    $a[$i]['desc'] = $a[$i][2];
                }
            }
        }

        return $a;
    }

    /**
     * Find a member
     *
     * (ie: <domain.com>/mailman/admin/<listname>/members?findmember=<email-address>
     *      &setmemberopts_btn&adminpw=<adminpassword>)
     *
     * @param string $string A search string for member
     *
     * @return array Return an array of members (and their options) that match the string
     *
     * @throws MailmanServiceException
     */
    public function member(string $list, string $string): array
    {
        $path  = '/admin/' . $list . '/members';
        $query = array(
            'findmember'        => $string,
            'setmemberopts_btn' => null,
            'adminpw'           => $this->lists[$list],
        );

        $crawler = $this->getCrawler($path, $list, $query);

        $queries = array();
        $queries['address'] = $crawler->filterXPath('//body/form/center/table/tr/td[2]/a');
        $queries['realname'] = $crawler->filterXPath('//body/form/center/table/tr/td[2]/input[type=TEXT]/@value');
        $queries['mod'] = $crawler->filterXPath('//body/form/center/table/tr/td[3]/center/input/@value');
        $queries['hide'] = $crawler->filterXPath('//body/form/center/table/tr/td[4]/center/input/@value');
        $queries['nomail'] = $crawler->filterXPath('//body/form/center/table/tr/td[5]/center/input/@value');
        $queries['ack'] = $crawler->filterXPath('//body/form/center/table/tr/td[6]/center/input/@value');
        $queries['notmetoo'] = $crawler->filterXPath('//body/form/center/table/tr/td[7]/center/input/@value');
        $queries['nodupes'] = $crawler->filterXPath('//body/form/center/table/tr/td[8]/center/input/@value');
        $queries['digest'] = $crawler->filterXPath('//body/form/center/table/tr/td[9]/center/input/@value');
        $queries['plain'] = $crawler->filterXPath('//body/form/center/table/tr/td[10]/center/input/@value');
        $queries['language'] = $crawler->filterXPath('//body/form/center/table/tr/td[11]/center/select/option[@selected]/@value');
        $count = count($queries['address']);
        if (!$count) {
            throw new MailmanServiceException(
                'No match',
                MailmanServiceException::NO_MATCH
            );
        }

        $a = array();
        for ($i=0;$i < $count;$i++) {
            /** @var Crawler $query */
            foreach ($queries as $key => $query) {
                $a[$i][$key] = $query->getNode($i) ? $query->getNode($i)->nodeValue : '';
            }
        }

        return $a;
    }

    public function modAll( string $list, bool $on = true)
    {
        $path  = '/admin/' . $list . '/members';
        $crawler = $this->getCrawler($path, $list);
        $form = $crawler->selectButton('allmodbit_btn')->form();

        $value = $on ? '1' : '0';
        $form['allmodbit_val'] = $value;
        $this->httpBrowser->submit($form);
    }

    public function modSubscriber( string $list, string $email, bool $on = true)
    {
        $path  = '/admin/' . $list . '/members';
        $crawler = $this->getCrawler($path, $list);
        $letters = $crawler->filterXPath('//body/form/center[1]/table/tr[2]/td/center/a');
        if (count($letters)) {
            $letter = $email[0];
            $path  = '/admin/' . $list . '/members?letter='.$letter;
            $crawler = $this->getCrawler($path, $list);
        }

//        $crawler = $this->httpBrowser->submitForm('findmember_btn', [
//            'findmember' => $email,
//        ]);

        $emailEncoded = urlencode($email);
        $form = $crawler->selectButton('setmemberopts_btn')->form();
        if (!isset($form[$emailEncoded.'_realname'])) { // no row for this subscriber
            throw new MailmanServiceException('No such subscriber', MailmanServiceException::NO_MATCH);
        }
        /** @var ChoiceFormField $mod */
        $mod = $form[$emailEncoded.'_mod'];
        if ($on) {
            $mod->tick();
        } else {
            $mod->untick();
        }
        $form['user'] = $emailEncoded;
        $this->httpBrowser->submit($form);
    }

    /**
     * Unsubscribe
     *
     * (ie: <domain.com>/mailman/admin/<listname>/members/remove?send_unsub_ack_to_this_batch=0
     *      &send_unsub_notifications_to_list_owner=0&unsubscribees=<email-address>&adminpw=<adminpassword>)
     *
     * @param string $email Valid email address of a member to unsubscribe
     *
     * @return Mailman
     *
     * @throws MailmanServiceException
     */
    public function unsubscribe(string $list, string $email): ?Mailman
    {
        $path = '/admin/' . $list . '/members/remove';
        $query = array(
            'send_unsub_ack_to_this_batch' => 0,
            'send_unsub_notifications_to_list_owner' => 0,
            'unsubscribees' => $email,
            'adminpw' => $this->lists[$list],
        );
        $crawler = $this->getCrawler($path, $list, $query);
        $h5 = $crawler->filterXPath('//body/h5');
        $h3 = $crawler->filterXPath('//body/h3');
        if ($h5->getNode(0) && $h5->getNode(0)->nodeValue == 'Successfully Unsubscribed:') {
            return $this;
        }
        if ($h3->getNode(0)) {
            throw new MailmanServiceException(
                trim($h3->getNode(0)->nodeValue, ':'),
                MailmanServiceException::HTML_PARSE
            );
        }
        throw new MailmanServiceException(
            'Failed to parse HTML',
            MailmanServiceException::HTML_PARSE
        );
    }

    /**
     * Subscribe
     *
     * (ie: http://example.co.uk/mailman/admin/test_example.co.uk/members/add
     * ?subscribe_or_invite=0&send_welcome_msg_to_this_batch=1
     * &send_notifications_to_list_owner=0&subscribees=test%40example.co.uk
     * &invitation=&setmemberopts_btn=Submit+Your+Changes)
     *
     * @param string  $email  Valid email address to subscribe
     * @param boolean $invite Send an invite or not (default)
     *
     * @return Mailman|bool
     *
     * @throws MailmanServiceException
     */
    public function subscribe(string $list, string $email, bool $invite = false): ?Mailman
    {
        $path = '/admin/' . $list . '/members/add';
        $query = array(
            'subscribe_or_invite' => (int) $invite,
            'send_welcome_msg_to_this_batch' => 0,
            'send_notifications_to_list_owner' => 0,
            'subscribees' => $email,
            'adminpw' => $this->lists[$list]);
        $crawler = $this->getCrawler($path, $list, $query);
        $h5 = $crawler->filterXPath('//body/h5');
        if (!is_object($h5) || count($h5) == 0) {
            return false;
        }
        if ($value = $h5->getNode(0)->nodeValue) {
            if ($value == 'Successfully subscribed:' || $value == 'Successfully invited:') {
                return $this;
            } else {
                $errorMsg = $crawler->filterXPath('//body/ul/li')->text();
                throw new MailmanServiceException(
                    trim($value).' '.trim($errorMsg),
                    MailmanServiceException::USER_INPUT
                );
            }
        }

        return false;
    }

    public function isSubscribed(string $list, string $email): bool
    {
        try {
            $searchResults = $this->member($list, $email);
        }
        catch(MailmanServiceException $e) {
            if (6 == $e->getCode()) {
                return false;
            } else { // throw the exception again
                throw new MailmanServiceException($e->getMessage(), $e->getCode());
            }
        }
        foreach ($searchResults as $subscriber) {
            if (!strcasecmp($email, $subscriber["address"])) {
                return true;
            }
        }

        return false;
    }


    /**
     * Set digest. Note that the $email needs to be subsribed first
     *  (e.g. by using the {@link subscribe()} method)
     *
     * (ie: <domain.com>/mailman/admin/<listname>/members?user=<email-address>
     *      &<email-address>_digest=1&setmemberopts_btn=Submit%20Your%20Changes
     *      &allmodbit_val=0&<email-address>_language=en&<email-address>_nodupes=1
     *      &adminpw=<adminpassword>)
     *
     * @param string $email  Valid email address of a member
     *
     * @param bool   $digest Set the Digest on (1) or off (0)
     *
     * @return string Returns 1 if set on, or 0 if set off.
     *
     * @throws MailmanServiceException
     */
    public function setDigest(string $list, string $email, string $digest = '1'): string
    {
        return $this->setOption($list, $email, 'digest', $digest ? '1' :'0');
    }

    /**
     * Set an option
     *
     * @param string $email  Valid email address of a member
     *
     * @param string $option A valid option (new-address, fullname, newpw, disablemail, digest, mime, dontreceive, ackposts, remind, conceal, rcvtopic, nodupes)
     *
     * @param string $value  A value for the given option
     *
     * @return string Returns resulting value, if successful.
     *
     * @throws MailmanServiceException
     */
    public function setOption(string $list, string $email, string $option, string $value): string
    {
        $path = '/options/' . $list . '/' . str_replace('@', '--at--', $email);
        if ($option == 'new-address') {
            $query['new-address'] = $value;
            $query['confirm-address'] = $value;
            $query['change-of-address'] = 'Change+My+Address+and+Name';
            $xp = "//input[@name='$option']/@value";
        } elseif ($option == 'fullname') {
            $query['fullname'] = $value;
            $query['change-of-address'] = 'Change+My+Address+and+Name';
            $xp = "//input[@name='$option']/@value";
        } elseif ($option == 'newpw') {
            $query['newpw'] = $value;
            $query['confpw'] = $value;
            $query['changepw'] = 'Change+My+Password';
            $xp = "//input[@name='$option']/@value";
        } elseif ($option == 'disablemail') {
            $query['disablemail'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'digest') {
            $query['digest'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'mime') {
            $query['mime'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'dontreceive') {
            $query['dontreceive'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'ackposts') {
            $query['ackposts'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'remind') {
            $query['remind'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'conceal') {
            $query['conceal'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'rcvtopic') {
            $query['rcvtopic'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } elseif ($option == 'nodupes') {
            $query['nodupes'] = $value;
            $query['options-submit'] = 'Submit+My+Changes';
            $xp = "//input[@name='$option' and @checked]/@value";
        } else {
            throw new MailmanServiceException(
                'Invalid option',
                MailmanServiceException::INVALID_OPTION
            );
        }
        $crawler = $this->getCrawler($path, $list, $query)->filterXPath($xp);
        if ($node = $crawler->getNode(0)) {
            return $node->nodeValue;
        }
        throw new MailmanServiceException(
            'Failed to parse HTML',
            MailmanServiceException::HTML_PARSE
        );
    }

    /**
     * List members
     *
     * @return array  Returns two nested arrays, the first contains email addresses, the second contains names
     */
    public function members(string $list): array
    {
        $path  = '/admin/' . $list . '/members';
        $crawler = $this->getCrawler($path, $list);
        $letters = $crawler->filterXPath('//body/form/center[1]/table/tr[2]/td/center/a');

        if (count($letters)) {
            $letters = $letters->each(function (Crawler $node) {
                return strtolower(trim($node->text(), '[]')) ;
            });
        } else {
            $letters = [null];
        }
        $members = array(array(), array());
        foreach ($letters as $letter) {
            $query = array('adminpw' => $this->lists[$list]);
            if ($letter != null) {
                $query['letter'] = $letter;
                $crawler = $this->getCrawler($path, $list, $query);
            }
            $emails = $crawler->filterXPath('//html/body/form/center[1]/table/tr/td[2]/a');
            $names = $crawler->filterXPath('//body/form/center[1]/table/tr/td[2]/input[1]/@value');
            $count = count($emails);
            for ($i=0;$i < $count;$i++) {
                if ($emails->eq($i)) {
                    $members[0][]=$emails->getNode($i)->nodeValue;
                }
                if ($names->eq($i)) {
                    $members[1][]=$names->getNode($i)->nodeValue;
                }
            }
        }

        return $members;
    }
    /**
     * Version
     *
     * @return string Returns the version of Mailman
     *
     * @throws MailmanServiceException
     */
    public function version(string $list): string
    {
        $path = '/admin/' . $list . '/';
        $crawler = $this->getCrawler($path, $list);
        $content = $crawler->filterXPath('//table[last()]')->eq(0)->text();
        if (preg_match('#version ([\d-.]+)#is', $content, $m)) {
            return array_pop($m);
        }
        throw new MailmanServiceException(
            'Failed to parse HTML',
            MailmanServiceException::HTML_PARSE
        );
    }
//    /**
//     * Parse and Return General List info
//     *
//     * (ie: <domain.com>/mailman/admin/<listname>)
//     *
//     * @param string $string list name
//     *
//     * @return array Return an array of list information
//     *
//     * @throws MailmanServiceException
//     */
//    public function listinfo(string $list): array
//    {
//        $path  = '/' . $list;
//        $crawler = $this->getCrawler($path, $list);
//        $a = array();
//        $queries = array();
//        $queries[] = $crawler->filterXPath("//input");
//        $queries[] = $crawler->filterXPath("//textarea");
//        $ignore_types = array(
//            'submit',
//            'hidden',
//        );
//        //get inputs
//
//        foreach ($queries as $query) {
//            foreach ($query as $item) {
//                /** @var DomElement $item */
//                $type = strtolower($item->getAttribute('type'));
//                $type = (empty($type)) ? 'textarea' : $type;
//                if (in_array($type, $ignore_types)) {
//                    continue; //ignore defined types
//                }
//
//                $name = $item->getAttribute('name');
//                $value = ($type === 'textarea') ? $item->nodeValue : $item->getAttribute('value');
//                $checked = $item->getAttribute('checked');
//
//                //initialize checkbox array if it's not set
//                if ($type === 'checkbox' && !isset($a[$name])) {
//                    $a[$name] = array();
//                }
//
//                //skip non checked values
//                if ($type === 'radio' && $checked !== 'checked')  {
//                    continue;
//                }
//                if ($type === 'checkbox' && $checked !== 'checked') {
//                    continue;
//                }
//
//                if ($type === 'checkbox') {
//                    $a[$name][] = $value;
//                } else {
//                    $a[$name] = $value;
//                }
//            }
//        }
//        ksort($a);
//
//        return $a;
//    }

    /**
     * from ghanover/mailman-sync
     * @param $list
     * @return array
     */
    public function roster($list)
    {
        $hasAdminCookie = false;
        /** @var Cookie $cookie */
        foreach( $this->httpBrowser->getCookieJar()->all() as $cookie) {
            if ($list.'+admin' == $cookie->getName()) {
                $hasAdminCookie = true;
            }
        };
        if (!$hasAdminCookie) { // set admin cookie
            $crawler = $this->httpBrowser->request(
                'GET',
                $this->baseUri.'/admin/'.$list
            );
            $form = $crawler->selectButton("admlogin")->form();
            $form['adminpw'] = 'swanee03';
            $this->httpBrowser->submit($form);
        }

        // now get and process the page
        $path = '/roster/'.$list;
        $crawler = $this->getCrawler($path, $list);
        $members = $crawler->filterXPath('//li/a')->each(function (Crawler $node) {
            return str_replace(' at ', '@', $node->text());
        });

        return $members;
    }



    /**
     * from ghanover/mailman-sync
     * @param string $list
     * @return bool
     * @throws MailmanServiceException
     */
    public function change(string $list, string $emailFrom, string $emailTo): bool
    {
        $path = '/admin/' . $list . '/members/change';
        $query = [
            'change_from' => $emailFrom,
            'change_to' => $emailTo,
            'notice_old' => 0,
            'notice_new' => 0,
            'adminpw' => $this->lists[$list],
        ];
        $crawler = $this->getCrawler($path, $list, $query);
        $html = $crawler->html();
        if (strstr($html, 'is not a member')) {
            throw new MailmanServiceException($emailTo.' is already a list member',
                MailmanServiceException::USER_INPUT);
        }
        if (strstr($html, 'is already a list member')) {
            throw new MailmanServiceException($emailTo.' is already a list member',
                MailmanServiceException::USER_INPUT);
        }

        return true;
    }

    /**
     * @param string $path
     * @param string $list
     * @param array $query
     * @param string $method
     *
     * @return Crawler
     */
    private function getCrawler(string $path, string $list = '', array $query = [], string $method = 'GET'): Crawler
    {
        if ($list) {
            $query['adminpw'] = $this->lists[$list];
        }

        $uri = $this->baseUri.$path;

        $parameters = [];
        if ('GET' === $method) {
            $query = http_build_query($query, '', '&');
            $uri .= '?'.$query;
        } else {
            $parameters['body'] = $query;
        }
        $crawler = $this->httpBrowser->request($method, $uri, $parameters);

        return $crawler;
    }
}
