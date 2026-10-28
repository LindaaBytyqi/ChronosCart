<?php

namespace App\Form;

use App\Entity\FlashSaleEvent;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlashSaleEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startTime', null, [
                'widget' => 'single_text'
            ])
            ->add('endTime', null, [
                'widget' => 'single_text'
            ])
            ->add('maxQuantityPerUser')
            ->add('discountPercentage')
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'placeholder' => 'Select a product',
                'choice_label' => 'name',
                'label' => 'Product',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FlashSaleEvent::class,
        ]);
    }
}
