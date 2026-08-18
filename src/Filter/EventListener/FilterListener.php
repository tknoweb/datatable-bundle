<?php

namespace Tknoweb\DatatableBundle\Filter\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Tknoweb\DatatableBundle\Datatable\Datatable;

/**
 * Filter forms EventListener handling session management
 */
class FilterListener implements EventSubscriberInterface
{
    public function __construct(protected RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

    public function onPreSetData(FormEvent $event): void
    {
        $form = $event->getForm();

        $sessionName = $form->getConfig()->getOption('datatable')->getSessionName() ;
        $formSessionData = $this->requestStack->getSession()->get($sessionName);

        //Retrieve the session filters
        $formSessionFilters = null;
        if($formSessionData && isset($formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME] )) {
            $formSessionFilters = $formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME];
        }

        //If there is a forced filter parameters given
        if($this->requestStack->getCurrentRequest()->get(Datatable::FORCE_FILTERS_PARAMETER_NAME)) {
            //Remove session filters and force the filter
            unset($formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME]);
            //Retrieve the forced filter
            $forcedFilter = $this->requestStack->getCurrentRequest()->get(Datatable::FORCE_FILTERS_PARAMETER_NAME);

            //Add the force filter in session
            $formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME] = $forcedFilter;
            $this->requestStack->getSession()->set($sessionName, $formSessionData);

            //Force the filter
            $event->setData($forcedFilter);
        } else {
            //If there's any filter in session
            if ($formSessionFilters) {
                //If the filters have to be reset
                if ($this->requestStack->getCurrentRequest()->get(Datatable::RESET_FILTERS_PARAMETER_NAME)) {
                    unset($formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME]);
                    $this->requestStack->getSession()->set($sessionName, $formSessionData);
                } else {
                    //Load the filters from the session
                    $event->setData($formSessionFilters);
                }
            }
        }
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();
        $form = $event->getForm();

        $sessionName = $form->getConfig()->getOption('datatable')->getSessionName() ;
        $formSessionData = $this->requestStack->getSession()->get($sessionName, []);
        $formSessionData[Datatable::DATATABLE_SESSION_FILTERS_NAME] = $data;
        //Set the filters in session
        $this->requestStack->getSession()->set($sessionName, $formSessionData);
    }
}