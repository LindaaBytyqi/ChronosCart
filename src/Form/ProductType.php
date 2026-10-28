<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;


class ProductType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('sku', TextType::class, [
                'label' => 'SKU',
                'attr' => [
                    'placeholder' => 'Example: WATCH-001',
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Product name',
                'attr' => [
                    'placeholder' => 'Enter product name',
                ],
            ])
            ->add('basePrice', NumberType::class, [
                'label' => 'Base price (€)',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => '0.01',
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('initialStock', IntegerType::class, [
                'label' => 'Initial stock',
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Enter stock quantity',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Product status',
                'choices' => [
                    'Draft' => 'DRAFT',
                    'Active' => 'ACTIVE',
                    'Expired' => 'EXPIRED',
                ],
                'placeholder' => 'Choose product status',
            ])
                    
            ->add('imageFile', FileType::class, [
                'label' => 'Product image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                new File(
                    maxSize: '5M',
                    mimeTypes: [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ],
                mimeTypesMessage: 'Please upload a valid JPG, PNG or WEBP image.',
                ),
            ],
        ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}