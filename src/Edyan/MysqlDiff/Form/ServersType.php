<?php
namespace Edyan\MysqlDiff\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ServersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Hosts
        for ($i = 1; $i <= 2; $i++) {
            $builder
                ->add("host_{$i}", 'text', [
                    'property_path' => "[$i][host]",
                    'constraints' => [
                        new Assert\Length(['min' => 3, 'max' => 70]),
                        new Assert\NotBlank(),
                        new Assert\Regex(['pattern' => '/[a]/']),
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

    public function getName()
    {
        return 'options_servers';
    }
}
