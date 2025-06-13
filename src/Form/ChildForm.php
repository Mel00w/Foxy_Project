<?php

namespace App\Form;

use App\Entity\Child;
use App\Entity\Team;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ChildForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Informations de l'enfant
            ->add('name', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ])
            ->add('birthday', DateType::class, [
                'label' => 'Date de naissance',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('picture', FileType::class, [
                'label' => 'Photo',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG ou WEBP)',
                    ])
                ],
            ])
            ->add('allergy', TextareaType::class, [
                'label' => 'Allergies',
                'required' => false,
            ])
            ->add('health_condition', TextareaType::class, [
                'label' => 'Conditions de santé',
                'required' => false,
            ])
            ->add('signing_date', DateType::class, [
                'label' => 'Date d\'inscription',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('team', EntityType::class, [
                'class' => Team::class,
                'choice_label' => 'name',
                'label' => 'Équipe',
                'required' => false,
                'help' => 'L\'équipe sera attribuée automatiquement en fonction de l\'âge de l\'enfant.',
                'placeholder' => 'Attribution automatique',
            ])

            // Informations du Parent 1
            ->add('parent1_name', TextType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Prénom'
            ])
            ->add('parent1_lastname', TextType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Nom'
            ])
            ->add('parent1_email', EmailType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Email'
            ])
            ->add('parent1_password', PasswordType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Mot de passe'
            ])
            ->add('parent1_birthday', DateType::class, [
                'mapped' => false,
                'required' => true,
                'widget' => 'single_text',
                'label' => 'Date de naissance'
            ])
            ->add('parent1_phone', TelType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Téléphone'
            ])
            ->add('parent1_lien', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'choices' => [
                    'Père' => 'pere',
                    'Mère' => 'mere',
                    'Tuteur' => 'tuteur',
                    'Autre' => 'autre'
                ],
                'label' => 'Lien'
            ])
            ->add('parent1_adress', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Adresse'
            ])
            ->add('parent1_income', NumberType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Revenu'
            ])

            // Informations du Parent 2
            ->add('parent2_name', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Prénom'
            ])
            ->add('parent2_lastname', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nom'
            ])
            ->add('parent2_email', EmailType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Email'
            ])
            ->add('parent2_password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Mot de passe'
            ])
            ->add('parent2_birthday', DateType::class, [
                'mapped' => false,
                'required' => false,
                'widget' => 'single_text',
                'label' => 'Date de naissance'
            ])
            ->add('parent2_phone', TelType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Téléphone'
            ])
            ->add('parent2_lien', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'choices' => [
                    'Père' => 'pere',
                    'Mère' => 'mere',
                    'Tuteur' => 'tuteur',
                    'Autre' => 'autre'
                ],
                'label' => 'Lien'
            ])
            ->add('parent2_adress', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Adresse'
            ])
            ->add('parent2_income', NumberType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Revenu'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Child::class,
        ]);
    }
}
