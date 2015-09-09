<?php
/**
 * @todo   prepare 2 sql files with DBs to import and test
 * @todo   check all options in "what to compare"
 */

namespace Edyan\MySQLDiff\Tests;

use Silex\WebTestCase;

class YourTest extends WebTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../www/index.php';
        $app['session.test'] = true;
        $app['debug'] = true;
        unset($app['exception_handler']);

        return $app;
    }

    public function testHome()
    {
        $client = $this->createClient();
        $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isRedirect('/options/servers'));
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessageRegExp |No route found for "GET /foo"|
     */
    public function test404()
    {
        $client = $this->createClient();
        $client->request('GET', '/foo');
    }


    public function testGetServersConfig()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/options/servers');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertGreaterThan(0, $crawler->filter('html:contains("Servers Informations")')->count());
        $this->assertCount(0, $crawler->filter('div.alert-danger'));
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessageRegExp |Your form is empty|
     */
    public function testPostBadFormForServersConfig()
    {
        $client = $this->createClient();
        $client->request('POST', '/options/servers', ['foo' => 'bar']);
    }

    public function testPostMissingDataInFormForServersConfig()
    {
        $client = $this->createClient();
        // Generate CSRF for my_form
        $crawler = $client->request('GET', '/options/servers');
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertGreaterThan(0, $crawler->filter('html:contains("Servers Informations")')->count());
        $form = $crawler->selectButton('Continue')->form();
        // POST data
        $postedData = [
            'options_servers' => [
                'host_1' => 'foo'
            ]
        ];
        $crawler = $client->submit($form, $postedData);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertEquals(1, $crawler->filter('html:contains("Servers Informations")')->count());
        $this->assertCount(3, $crawler->filter('div.alert-danger'));
        $this->assertEquals(1, $crawler->filter('html:contains("Your form is not valid (Wrong validation or missing CSRF)")')->count());
        $this->assertGreaterThan(0, $crawler->filter('html:contains("Failed trying to connect to Host (foo)")')->count());
    }


    public function testPostOnlyOneRightDBInFormForServersConfig()
    {
        $client = $this->createClient();
        // Generate CSRF for my_form
        $crawler = $client->request('GET', '/options/servers');
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertEquals(1, $crawler->filter('html:contains("Servers Informations")')->count());
        $form = $crawler->selectButton('Continue')->form();
        // POST
        $postedData = [
            'options_servers' => [
                'host_1' => getenv('host_1'),
                'user_1' => getenv('user_1'),
                'password_1' => getenv('password_1'),
            ]
        ];
        $crawler = $client->submit($form, $postedData);

        $this->assertEquals(1, $crawler->filter('div.alert-success')->count());
        $this->assertEquals(2, $crawler->filter('div.alert-danger')->count());
        $this->assertEquals(1, $crawler->filter('html:contains("Your form is not valid")')->count());
    }


    protected function postRightDataInFormForServersConfig()
    {
        $client = $this->createClient();
        // Generate CSRF for my_form
        $crawler = $client->request('GET', '/options/servers');
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertEquals(1, $crawler->filter('html:contains("Servers Informations")')->count());
        $form = $crawler->selectButton('Continue')->form();
        // POST
        $postedData = [
            'options_servers' => [
                'host_1' => getenv('host_1'),
                'user_1' => getenv('user_1'),
                'password_1' => getenv('password_1'),
                'host_2' => getenv('host_2'),
                'user_2' => getenv('user_2'),
                'password_2' => getenv('password_2'),
            ]
        ];
        $crawler = $client->submit($form, $postedData);

        // Redirects to next page
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->isRedirect('/options/databases'));

        // Now get the databases list
        $client = $this->createClient();
        $crawler = $client->request('GET', '/options/databases');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertEquals(1, $crawler->filter('html:contains("Databases Selection")')->count());
        $this->assertCount(2, $crawler->filter('div.alert-success'));

        $db1 = $crawler->filter('select[name="options_databases[database_1]"] > option')->extract(array('value'));
        $this->assertGreaterThan(0, $db1);
        $db2 = $crawler->filter('select[name="options_databases[database_2]"] > option')->extract(array('value'));
        $this->assertGreaterThan(0, $db2);

        return $crawler;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |Input "options_databases\[database_1\]" cannot take "foo" as a value (.*)|
     */
    public function testWrongDBSelected()
    {
        $client = $this->createClient();
        $crawler = $this->postRightDataInFormForServersConfig();
        //select the form
        $form = $crawler->selectButton('Continue')->form();
        //submit the form passing an array of values
        $crawler = $client->submit($form, [
            'options_databases[database_1]' => 'foo',
            'options_databases[database_2]' => 'bar',
        ]);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }


    public function testRightDBSelected()
    {
        $client = $this->createClient();
        $crawler = $this->postRightDataInFormForServersConfig();
        //select the form
        $form = $crawler->selectButton('Continue')->form();
        //submit the form passing an array of values
        $crawler = $client->submit($form, [
            'options_databases[database_1]' => getenv('db_1'),
            'options_databases[database_2]' => getenv('db_2'),
        ]);

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->isRedirect('/options/what-to-compare'));
    }


    public function testWhatToCompareAndGetResult()
    {
        $this->testRightDBSelected();

        // Now get the databases list
        $client = $this->createClient();
        $crawler = $client->request('GET', '/options/what-to-compare');

        $checkboxes = $crawler->filter('input[type=checkbox]')->extract(array('name'));
        $this->assertCount(13, $checkboxes);
        //select the form
        $form = $crawler->selectButton('Continue')->form();
        //submit the form passing an array of values
        $crawler = $client->submit($form);

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->isRedirect('/results'));

        // Now get the result
        $crawler = $client->request('GET', '/results');
        $this->assertEquals(1, $crawler->filter('html:contains("Modifications")')->count());
        $message = 'Will be applied to '.getenv('db_1').' on '.getenv('host_1');
        $this->assertEquals(1, $crawler->filter('html:contains('.$message.')')->count());

        // I have a box with MySQL
        $this->assertEquals(1, $crawler->filter('pre.mysql')->count());
    }
}
