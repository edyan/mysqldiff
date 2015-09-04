<?php
namespace Edyan\MysqlDiff\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class WhatToCompareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Databases
        $builder
          // DONE
          ->add(
              'create_missing_tables',
              'checkbox',
              [
                'label' => 'Create missing tables',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'delete_extra_tables',
              'checkbox',
              [
                'label' => 'Delete non-existing tables',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'alter_table_options',
              'checkbox',
              [
                'label' => 'Alter table options (including Engine)',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              // DOCTRINE LIMITATION
              'check_auto_increment',
              'checkbox',
              [
                'label' => 'Consider auto_increment parameter (To be done)',
                'attr' => ['class' => 'checkbox-inline', 'disabled' => 'disabled'],
                'data' => false,
              ]
          )->add(
              // DOCTRINE LIMITATION
              'alter_table_charset',
              'checkbox',
              [
                'label' => 'Alter table charset (To be done)',
                'attr' => ['class' => 'checkbox-inline', 'disabled' => 'disabled'],
                'data' => false,
              ]
          )->add(
              'alter_columns',
              'checkbox',
              [
                'label' => 'Alter columns',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'alter_indexes',
              'checkbox',
              [
                'label' => 'Alter indexes',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              // OK For Columns but limited for Tables
              'alter_comments',
              'checkbox',
              [
                'label' => 'Alter comments',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'use_backticks',
              'checkbox',
              [
                'label' => 'Use Backticks for table and attribute names',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'syntax_highlighting',
              'checkbox',
              [
                'label' => 'Syntax highlighting',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'line_numbers',
              'checkbox',
              [
                'label' => 'Enable Line Numbers',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add(
              'alter_foreign_keys',
              'checkbox',
              [
                'label' => 'Add / Remove / Change foreign keys',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
             /**
              * Later create INSERT and REPLACE
              * )->add(
              *     'create_insert_replace',
              *     'checkbox',
              *     [
              *       'label' => 'Create INSERT or REPLACE-statements for selected tables',
              *       'attr' => ['class' => 'checkbox-inline'],
              *    'data' => true,
              *     ]
              */
          )->add(
              'deactivate_foreign_keys',
              'checkbox',
              [
                'label' => 'Deactivate foreign keys checks before script run',
                'attr' => ['class' => 'checkbox-inline'],
                'data' => true,
              ]
          )->add('continue', 'submit', ['attr' => ['class' => 'btn btn-primary']]);
    }

    public function getName()
    {
        return 'what_to_compare';
    }
}
