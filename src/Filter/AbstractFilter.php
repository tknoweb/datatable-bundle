<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base filter form field class, use it as an extended class for every filter field (Text, Bool ...)
 */
abstract class AbstractFilter extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $defaultValue = $event->getForm()->getConfig()->getOption('default_value');

            if($event->getData() === null && $defaultValue) {
                $event->setData($defaultValue);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'default_value' => null,
        ]);

        $resolver->setRequired('filter_query');
        $resolver->setAllowedTypes('filter_query', ['callable']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $defaultValue = $form->getConfig()->getOption('default_value');
        if ($defaultValue) {
            $view->vars['is_filtered'] = $defaultValue !== $form->getData();
        } else {
            $view->vars['is_filtered'] = $form->getData() !== null && $form->getData() !== '';
        }
    }
}