<?php

namespace Edyan\MysqlDiff\Controller;

use Edyan\MysqlDiff\Form;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class AppController
{
    public function getOptionsServers(Application $app)
    {
        $form = $app['form.factory']->create(new Form\ServersType());

        $_SESSION['servers'] = [];

        return $app['twig']->render('options-servers.html.twig', [
            'form' => $form->createView()
        ]);
    }

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

    public function getOptionsWhatToCompare(Application $app)
    {
        $form = $app['form.factory']->create(new Form\WhatToCompareType());

        return $app['twig']->render('options-what-to-compare.html.twig', [
            'form' => $form->createView()
        ]);
    }

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
        $schemaSqlDiff = $schemaDiff->toSql($dbs['dbhs'][1]->getDatabasePlatform());
        $sql = implode(';' . PHP_EOL, $schemaSqlDiff) . ';' . PHP_EOL;

        // Manage latest options
        if ($options['deactivate_foreign_keys']) {
            $sql = 'SET FOREIGN_KEY_CHECKS = 0;' . PHP_EOL . PHP_EOL . $sql;
            $sql.= PHP_EOL . 'SET FOREIGN_KEY_CHECKS = 1;';
        }
        // At the end
        if (!$options['use_backticks']) {
            $sql = str_replace('`', '', $sql);
        }
        // Highlight Syntax ?
        $css = '';
        if ($options['syntax_highlighting'] || $options['line_numbers']) {
            $geshi = new \GeSHi($sql, 'mysql');
            $geshi->enable_classes();
            $geshi->enable_keyword_links(false);

            if ($options['line_numbers']) {
                $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
            }
            if ($options['syntax_highlighting']) {
                $css = $geshi->get_stylesheet();
            }

            $sql = $geshi->parse_code();
        } else {
            $sql = "<pre>$sql</pre>";
        }

        return $app['twig']->render('results.html.twig', [
            'css' => $css,
            'sql' => $sql,
            'hosts' => $hosts
        ]);
    }

    private function getDatabases(array $dbs)
    {
        $data = [];
        $info = [];

        foreach ($dbs as $num => $db) {
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
            $schemaDiff->newTables = array();
        }

        // Drop existing tables ?
        if ($options['delete_extra_tables'] === false) {
            $schemaDiff->removedTables = array();
        }

        // Other Options
        if ($options['alter_table_options'] === false) {
            $schemaDiff->changedTables = array();
        } else {
            // Remove all Columns alteration in changedtables
            if ($options['alter_columns'] === false) {
                foreach ($schemaDiff->changedTables as $name => $props) {
                    $schemaDiff->changedTables[$name]->addedColumns =
                    $schemaDiff->changedTables[$name]->changedColumns =
                    $schemaDiff->changedTables[$name]->removedColumns =
                    $schemaDiff->changedTables[$name]->renamedColumns = array();
                }
            }

            // Remove all index definitions in changedtables
            if ($options['alter_indexes'] === false) {
                foreach ($schemaDiff->changedTables as $name => $props) {
                    $schemaDiff->changedTables[$name]->addedIndexes =
                    $schemaDiff->changedTables[$name]->changedIndexes =
                    $schemaDiff->changedTables[$name]->removedIndexes =
                    $schemaDiff->changedTables[$name]->renamedIndexes = array();
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
                    $schemaDiff->changedTables[$name]->removedForeignKeys = array();
                }
            }
        }

        return $schemaDiff;
    }
}
