<?php

namespace App\Form;

use App\Entity\ChildUser;
use App\Entity\Child;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class ChildUserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('child', EntityType::class, [
                'class' => Child::class,
                'choice_label' => function(Child $child) {
                    return $child->getName() . ' ' . $child->getLastname();
                },
                'choices' => $options['children'],
                'placeholder' => 'Sélectionner un enfant',
                'required' => true,
                'label' => 'Enfant'
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function(User $user) {
                    return $user->getName() . ' ' . $user->getLastname();
                },
                'choices' => $options['educators'],
                'placeholder' => 'Sélectionner un éducateur',
                'required' => true,
                'label' => 'Éducateur'
            ])
            ->add('lien', ChoiceType::class, [
                'choices' => [
                    'Éducateur principal' => 'principal',
                    'Éducateur secondaire' => 'secondaire'
                ],
                'required' => true,
                'label' => 'Type de lien'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChildUser::class,
            'children' => [],
            'educators' => []
        ]);
    }
} 