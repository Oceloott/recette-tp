<?php
namespace App\Form;

use App\Entity\Step;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddRecipeStepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description'
            ])
            ->add('stepOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['min' => 0],

            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Step::class,
        ]);
    }
}
