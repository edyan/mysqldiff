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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Symfony Form to set information about both DBs
 */
class ServersType extends AbstractType
{
    /**
     * Build the form
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $hostnameRegex = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/';
        // Hosts
        for ($i = 1; $i <= 2; $i++) {
            $builder
                ->add("host_{$i}", 'text', [
                    'property_path' => "[$i][host]",
                    'constraints' => [
                        new Assert\Length(['min' => 3, 'max' => 70]),
                        new Assert\NotBlank(),
                        new Assert\Regex(['pattern' => $hostnameRegex]),
                    ],
                    'label' => "Host $i",
                ])
                ->add("user_{$i}", 'text', [
                    'property_path' => "[$i][user]",
                    'constraints' => [
                        new Assert\Length(['min' => 3, 'max' => 70]),
                        new Assert\NotBlank(),
                    ],
                    'label' => "User for Host $i",
                ])
                ->add("password_{$i}", 'password', [
                    'property_path' => "[$i][password]",
                    'label' => "Password for Host $i",
                ]);
        }
        // Submit
        $builder->add('continue', 'submit');
    }

    /**
     * Get Form name
     *
     * @return string
     */
    public function getName()
    {
        return 'options_servers';
    }
}
