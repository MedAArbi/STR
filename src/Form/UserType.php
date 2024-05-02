<?php

namespace App\Form;
use Symfony\Component\Validator\Constraints\NotBlank;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{ public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom ne peut pas être vide.'),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-Z]+$/',
                        'message' => 'Le nom doit contenir uniquement des lettres alphabétiques.',
                    ]),
                ],
            ])
            ->add('mail', null, [
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse e-mail ne peut pas être vide.'),
                    new Assert\Email(['message' => 'L\'adresse e-mail est invalide.']),
                ],
            ])
            ->add('numTel', null, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numéro de téléphone ne peut pas être vide.'),
                    new Assert\Regex([
                        'pattern' => '/^[259]\d{7}$/',
                        'message' => 'Le numéro de téléphone doit commencer par 2, 5 ou 9 et contenir exactement 8 chiffres.',
                    ]),
                ],
            ])
            ->add('adresse', null, [
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse ne peut pas être vide.'),
                ],
            ])
            ->add('mdp', null, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe ne peut pas être vide.'),
                ],
            ])
        ;
    }
    
    

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
