<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Tknoweb\DatatableBundle\Datatable\Datatable;
use Tknoweb\DatatableBundle\Filter\EventListener\FilterListener;

/**
 * Base filter form class, use it as an extended class for every filter form
 */
abstract class AbstractBaseFilter extends AbstractType
{
    const FILTER_CLEAR_NAME = 'filterClear';
    const FILTER_SAVE_NAME = 'filterSave';

    public function __construct(protected RequestStack $requestStack)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(self::FILTER_CLEAR_NAME, ButtonType::class, ['label' => false])
            ->add(self::FILTER_SAVE_NAME, SubmitType::class, ['label' => 'filter'])
            ->addEventSubscriber(new FilterListener($this->requestStack));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false
        ]);
        $resolver->setRequired('datatable');
        $resolver->addAllowedTypes('datatable', Datatable::class);
    }

    public function getBlockPrefix(): string
    {
        return "form_filter_" . $this->requestStack->getCurrentRequest()->get('_route');
    }

    public function getParent(): ?string
    {
        return FormType::class;
    }
}