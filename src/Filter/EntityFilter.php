<?php

namespace Tknoweb\DatatableBundle\Filter;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityFilter extends AbstractFilter
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        //Select type multiple
        if( $options['multiple'] ) {
            $builder->addModelTransformer(new CallbackTransformer(
                function ($entityIdentifiers) use ($options) {
                    if( $entityIdentifiers === null || empty($entityIdentifiers) ) {
                        return null;
                    }

                    foreach($entityIdentifiers as $key => $entityIdentifier) {
                        $entityIdentifiers[$key] = $options['em']->getRepository($options['class'])->find($entityIdentifier);
                    }

                    return $entityIdentifiers;
                },
                function ($entities) {
                    if ($entities === null || empty($entities)) {
                        return null;
                    }

                    return $entities;
                }
            ));
        } else {
            //Select singular value
            $builder->addModelTransformer(new CallbackTransformer(
                function ($entityIdentifier) use ($options) {
                    if( $entityIdentifier === null ) {
                        return null;
                    }

                    return $options['em']->getRepository($options['class'])->find($entityIdentifier);
                },
                function ($entity) {
                    if ($entity === null) {
                        return null;
                    }
                    return $entity->getId();
                }
            ));
        }

        parent::buildForm($builder, $options);
    }

    public function getParent(): ?string
    {
        return EntityType::class;
    }
}