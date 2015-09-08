<?php
/**
 * edyan/mysqldiff
 *
 * PHP Version 5.4
 *
 * @author Emmanuel Dyan
 * @copyright 2015 - Emmanuel Dyan
 *
 * @package edyan/mysqldiff
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/mysqldiff
 */

namespace Edyan\MysqlDiff\Controller;

use Edyan\MysqlDiff\Form;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main Controller
 */
class AppController
{
    /**
     * Open the first page that lets configure both servers
     * That'll create the form, reset the Session and render the view
     *
     * @param Application $app Silex Application
     *
     * @return string HTML Rendered
     */
    public function getOptionsServers(Application $app)
    {
        $form = $app['form.factory']->create(new Form\ServersType());

        $_SESSION['servers'] = [];

        return $app['twig']->render('options-servers.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Second page: once posted information about the servers, check it and
     * either list the DBs or display the form again
     *
     * @param Application $app     Silex Application
     * @param Request     $request
     *
     * @return string HTML Rendered
     */
    public function postOptionsServers(Application $app, Request $request)
    {
        $form = $app['form.factory']->create(new Form\ServersType());
        $form->handleRequest($request);
        $data = $form->getData();

        // connect with the data I found
        $connectToDbs = $this->connectToDbs($data);
        if ($connectToDbs['errors'] > 0 || !$form->isValid()) {
            $app['session']->set('hosts', null);

            return $app['twig']->render('options-servers.html.twig', [
                'form' => $form->createView(),
                'info' => $connectToDbs['info'],
            ]);
        }

        // Everything is fine, send another form which is the selection of databases
        $app['session']->set('hosts', $data);
        $getDbs = $this->getDatabases($connectToDbs['dbhs']);
        $form = $app['form.factory']->create(new Form\DatabasesType(), null, $getDbs['data']);

        return $app['twig']->render('options-databases.html.twig', [
            'form' => $form->createView(),
            'info' => $getDbs['info'],
        ]);
    }

    /**
     * Third page: once selected both DBs, check it and
     * either retirect to the diff options or display the form again if any error occurs
     *
     * @param Application $app     Silex Application
     * @param Request     $request
     *
     * @return string HTML Rendered
     */
    public function postOptionsDatabases(Application $app, Request $request)
    {
        // connect
        $hosts = $app['session']->get('hosts');
        $connectToDbs = $this->connectToDbs($hosts);
        // get db list
        $getDbs = $this->getDatabases($connectToDbs['dbhs']);

        // check the form
        $form = $app['form.factory']->create(new Form\DatabasesType(), null, $getDbs['data']);
        $form->handleRequest($request);
        $data = $form->getData();

        if (!$form->isValid()) {
            return $app['twig']->render('options-databases.html.twig', [
                'form' => $form->createView(),
                'info' => $getDbs['info'],
            ]);
        }

        // form is OK
        foreach ($hosts as $num => &$host) {
            $host['dbname'] = $data[$num]['database'];
        }

        $app['session']->set('hosts', $hosts);

        return $app->redirect($app['url_generator']->generate('what-to-compare'));
    }

    /**
     * Fourth page: Just list the diff options
     *
     * @param Application $app Silex Application
     *
     * @return string HTML Rendered
     */
    public function getOptionsWhatToCompare(Application $app)
    {
        $form = $app['form.factory']->create(new Form\WhatToCompareType());

        return $app['twig']->render('options-what-to-compare.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Fifth page: Validate options of comparison then redirect to the result
     *
     * @param Application $app     Silex Application
     * @param Request     $request
     *
     * @return string HTML Rendered
     */
    public function postOptionsWhatToCompare(Application $app, Request $request)
    {
        $form = $app['form.factory']->create(new Form\WhatToCompareType());
        $form->handleRequest($request);
        $options = $form->getData();
        $app['session']->set('options', null);

        if (!$form->isValid()) {
            return $app['twig']->render('options-what-to-compare.html.twig', [
                'form' => $form->createView()
            ]);
        }

        $app['session']->set('options', $options);

        return $app->redirect($app['url_generator']->generate('results'));
    }

    /**
     * Lsat page: Generate the SQL with the DIFF
     *
     * @param Application $app Silex Application
     *
     * @return string HTML Rendered
     */
    public function getResults(Application $app)
    {
        $options = $app['session']->get('options');
        $hosts = $app['session']->get('hosts');

        if (empty($options) || empty($hosts)) {
            return $app['twig']->render('error.html.twig', [
                'message' => 'Why are you coming directly there ?',
            ]);
        }

        // Try to connect to DBs
        $dbs = $this->connectToDbs($hosts);
        foreach ($dbs['info'] as $db) {
            if ($db['type_alert'] == 'danger') {
                return $app['twig']->render('error.html.twig', [
                    'message' => "Couldn't connect to one of the DBs",
                ]);
            }
        }

        // First get the tables list
        $dbalTables = [];
        $dbalSchemas = [];
        foreach ($dbs['dbhs'] as $hostId => $dbh) {
            try {
                // Get Schema Manager
                $sm = $dbh->getSchemaManager();
                // Save Tables as \Doctrine\DBAL\Schema\Table
                foreach ($sm->listTables() as $dbalTable) {
                    $dbalTables[$hostId][$dbalTable->getName()] = $dbalTable;
                }
                // Save Schemas
                $dbalSchemas[$hostId] = new \Doctrine\DBAL\Schema\Schema($dbalTables[$hostId]);
            } catch (\Exception $e) {
                return $app['twig']->render('error.html.twig', [
                    'message' => 'Error trying to get the list of Tables. ' . $e->getMessage(),
                ]);
            }
        }

        $schemaDiff = $this->buildSchemaDiff($dbalSchemas[2], $dbalSchemas[1], $options);
        // Build the SQL
        $generatedSQL = $this->finalizeSQLSyntax(
            $schemaDiff->toSql($dbs['dbhs'][1]->getDatabasePlatform()),
            $options['deactivate_foreign_keys'],
            $options['use_backticks'],
            $options['syntax_highlighting'],
            $options['line_numbers']
        );

        return $app['twig']->render('results.html.twig', [
            'css' => $generatedSQL['css'],
            'sql' => $generatedSQL['sql'],
            'hosts' => $hosts
        ]);
    }

    /**
     * Get a list of databases for all connections
     *
     * @param array $dbhs List of connections
     *
     * @return array List of databases + info in case of errors
     */
    private function getDatabases(array $dbhs)
    {
        $data = [];
        $info = [];

        foreach ($dbhs as $num => $db) {
            try {
                foreach ($db->getSchemaManager()->listDatabases() as $row) {
                    $data['database_' . $num . '_values'][$row] = $row;
                }
                if (empty($data['database_' . $num . '_values'])) {
                    throw new \Exception('No database to retrieve !');
                }

                $info['database_' . $num] =  [
                    'message' => 'Fetched ' . count($data['database_' . $num . '_values']) . ' databases',
                    'type_alert' => 'success',
                    'icon_alert' => 'ok',
                ];
            } catch (\Exception $e) {
                $info['database_' . $num] =  [
                    'message' => 'Failed trying to get the DBs: ' . $e->getMessage(),
                    'type_alert' => 'danger',
                    'icon_alert' => 'remove',
                ];
                $data['database_' . $num . '_values'] = [];
            }
        }

        return ['data' => $data, 'info' => $info];
    }

    /**
     * Connect to the DBs with Doctrine
     *
     * @param array $hosts
     *
     * @return array
     */
    private function connectToDbs(array $hosts)
    {
        $errors = 1;
        $info = [];
        $dbhs = [];
        $i = 1;

        foreach ($hosts as $host) {
            // Try to connect the host and select the DB if available
            try {
                $connectionParams = array_merge(array('driver' => 'pdo_mysql'), $host);
                $dbhs[$i] = \Doctrine\DBAL\DriverManager::getConnection(
                    $connectionParams,
                    new \Doctrine\DBAL\Configuration()
                );
                // force to trigger an exception by gettint the errorInfo
                $dbhs[$i]->errorInfo();
                $info[$i] = [
                    'message' => "Success connecting to Host ({$host['host']}) !",
                    'type_alert' => 'success',
                    'icon_alert' => 'ok',
                ];
                $errors--;
            } catch (\Exception $e) {
                $info[$i] =  [
                    'message' => "Failed trying to connect to Host ({$host['host']}): " . $e->getMessage(),
                    'type_alert' => 'danger',
                    'icon_alert' => 'remove',
                ];
                $errors++;
            }
            $i++;
        }

        return ['info' => $info, 'errors' => $errors, 'dbhs' => $dbhs];
    }

    /**
     * Build the schemadiff with Doctrine
     *
     * @param \Doctrine\DBAL\Schema\Schema $leftDbalSchemas
     * @param \Doctrine\DBAL\Schema\Schema $rightdbalSchemas
     * @param array                        $options
     *
     * @return \Doctrine\DBAL\Schema\SchemaDiff
     */
    private function buildSchemaDiff(
        \Doctrine\DBAL\Schema\Schema $leftDbalSchemas,
        \Doctrine\DBAL\Schema\Schema $rightdbalSchemas,
        array $options
    ) {
        // Compare Schemas
        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $schemaDiff = $comparator->compare($leftDbalSchemas, $rightdbalSchemas);

        // Create missing tables ?
        if ($options['create_missing_tables'] === false) {
            $schemaDiff->newTables = [];
        }

        // Drop existing tables ?
        if ($options['delete_extra_tables'] === false) {
            $schemaDiff->removedTables = [];
        }

        // Other Options
        if ($options['alter_table_options'] === false) {
            $schemaDiff->changedTables = [];
        } else {
            // Remove all Columns alteration in changedtables
            if ($options['alter_columns'] === false) {
                foreach ($schemaDiff->changedTables as $name => $props) {
                    $schemaDiff->changedTables[$name]->addedColumns =
                    $schemaDiff->changedTables[$name]->changedColumns =
                    $schemaDiff->changedTables[$name]->removedColumns =
                    $schemaDiff->changedTables[$name]->renamedColumns = [];
                }
            }

            // Remove all index definitions in changedtables
            if ($options['alter_indexes'] === false) {
                foreach ($schemaDiff->changedTables as $name => $props) {
                    $schemaDiff->changedTables[$name]->addedIndexes =
                    $schemaDiff->changedTables[$name]->changedIndexes =
                    $schemaDiff->changedTables[$name]->removedIndexes =
                    $schemaDiff->changedTables[$name]->renamedIndexes = [];
                }
            }
            // Remove all FK definitions in changedtables
            if ($options['alter_foreign_keys'] === false) {
                foreach ($schemaDiff->newTables as $name => $props) {
                    $fks = $schemaDiff->newTables[$name]->getForeignKeys();
                    foreach ($fks as $fkName => $dbalFk) {
                        $schemaDiff->newTables[$name]->removeForeignKey($fkName);
                    }
                }

                foreach ($schemaDiff->changedTables as $name => $props) {
                    $schemaDiff->changedTables[$name]->addedForeignKeys =
                    $schemaDiff->changedTables[$name]->changedForeignKeys =
                    $schemaDiff->changedTables[$name]->removedForeignKeys = [];
                }

                $schemaDiff->orphanedForeignKeys = [];
            }
        }

        return $schemaDiff;
    }

    /**
     * Put the last options to the sql: highlight, backticks....
     *
     * @param string  $sql
     * @param boolean $deactivateForeignKeys
     * @param boolean $backticks
     * @param boolean $highlight
     * @param boolean $lineNumbers
     *
     * @return string
     */
    private function finalizeSQLSyntax($sql, $deactivateForeignKeys, $backticks, $highlight, $lineNumbers)
    {
        $sql = implode(';' . PHP_EOL, $sql) . ';' . PHP_EOL;

        // Set line numbers, backticks....
        // Manage latest options
        if ($deactivateForeignKeys) {
            $sql = 'SET FOREIGN_KEY_CHECKS = 0;' . PHP_EOL . PHP_EOL . $sql;
            $sql.= PHP_EOL . 'SET FOREIGN_KEY_CHECKS = 1;';
        }
        // At the end
        if (!$backticks) {
            $sql = str_replace('`', '', $sql);
        }
        // Highlight Syntax ?
        $css = '';
        if ($highlight || $lineNumbers) {
            $geshi = new \GeSHi($sql, 'mysql');
            $geshi->enable_classes();
            $geshi->enable_keyword_links(false);

            if ($lineNumbers) {
                $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
            }
            if ($highlight) {
                $css = $geshi->get_stylesheet();
            }

            $sql = $geshi->parse_code();
        } else {
            $sql = "<pre>$sql</pre>";
        }

        return ['sql' => $sql, 'css' => $css];
    }
}
