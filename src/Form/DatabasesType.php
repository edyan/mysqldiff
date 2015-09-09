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

namespace Edyan\MysqlDiff\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Symfony Form to set information about both DBs
 */
class DatabasesType extends AbstractType
{
    /**
     * Build the form
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Databases
        for ($i = 1; $i <= 2; $i++) {
            $builder->add("database_{$i}", 'choice', [
                'property_path' => "[$i][database]",
                'choices' => $options["database_{$i}_values"],
                'label' => "Database on Host $i"
            ]);
        }
        // Submit
        $builder->add('continue', 'submit');
    }

    /**
     * Define required Fields
     *
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(['database_1_values', 'database_2_values']);
    }

    /**
     * Get Form name
     *
     * @return string
     */
    public function getName()
    {
        return 'options_databases';
    }
}
