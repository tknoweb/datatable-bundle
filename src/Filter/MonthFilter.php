<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MonthFilter extends AbstractFilter
{
    public function getParent(): ?string
    {
        return DateType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function (?string $stringToDateTime) {
                if ($stringToDateTime) {
                    return (\DateTime::createFromFormat('Y-m', $stringToDateTime))->modify('first day of this month');
                } else {
                    return null;
                }
            },
            function (?\DateTime $dateTimeToString) {
                if ($dateTimeToString === null) {
                    return null;
                }
                return $dateTimeToString->format('Y-m');
            }
        ));

        parent::buildForm($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'widget' => 'single_text',
            'format' => 'yyyy-MM',
            'html5' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'month_filter';
    }
}