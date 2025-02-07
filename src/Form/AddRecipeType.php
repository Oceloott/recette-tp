<?php

namespace App\Form;

use App\Entity\Recipe;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddRecipeType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Recette'
            ])
            ->add('description', TextType::class, [
                'label' => 'Description'
            ])
            ->add('prepTime', IntegerType::class, [
                'label' => 'Temps de préparation (minutes)',
                'attr' => ['min' => 0],
            ])
            ->add('cookTime', IntegerType::class, [
                'label' => 'Temps de cuisson (minutes)',
                'attr' => ['min' => 0],
            ])
            ->add('ingredients', CollectionType::class, [
                'entry_type' => AddRecipeIngredientType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,

            ])
            ->add('steps', CollectionType::class, [
                'entry_type' => AddRecipeStepType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
