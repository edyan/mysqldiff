<?php
namespace Edyan\MysqlDiff\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class DatabasesType extends AbstractType
{
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
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(['database_1_values', 'database_2_values']);
    }

    public function getName()
    {
        return 'options_databases';
    }
}
