<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) 2014-2016 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 2016-08-11T08:00:00Z
 */

namespace Prooph\ServiceBus\Plugin\Router;

use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Event\ActionEventEmitter;
use Prooph\Common\Event\ActionEventListenerAggregate;
use Prooph\Common\Event\DetachAggregateHandlers;
use Prooph\ServiceBus\Async\AsyncMessage;
use Prooph\ServiceBus\Async\MessageProducer;
use Prooph\ServiceBus\MessageBus;

/**
 * Class AsyncSwitchMessageRouter
 *
 * @package Prooph\ServiceBus\Router
 * @author Guy Radford <guyr@crazylime.co.uk>
 */
class AsyncSwitchMessageRouter implements MessageBusRouterPlugin, ActionEventListenerAggregate
{
    use DetachAggregateHandlers;

    /**
     * @var ActionEventListenerAggregate
     */
    protected $router;

    /**
     * @var MessageProducer
     */
    protected $asyncMessageProducer;


    /**
     * @param ActionEventListenerAggregate $router
     * @param MessageProducer $asyncMessageProducer
     */
    public function __construct(ActionEventListenerAggregate $router, MessageProducer $asyncMessageProducer)
    {
        $this->router = $router;
        $this->asyncMessageProducer = $asyncMessageProducer;
    }

    /**
     * @param ActionEventEmitter $events
     * @return void
     */
    public function attach(ActionEventEmitter $events)
    {
        $this->trackHandler($events->attachListener(MessageBus::EVENT_ROUTE, [$this, "onRouteMessage"]));
    }


    /**
     * @param ActionEvent $actionEvent
     */
    public function onRouteMessage(ActionEvent $actionEvent)
    {
        $messageName = (string)$actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        $message = $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE);

        //if the message is marked with AsyncMessage, but had not yet been sent via async then sent to async producer
        if ($message instanceof AsyncMessage && !(isset($message->metadata()['handled-async']) && $message->metadata()['handled-async'] === true)) {
            //apply meta data, this is need to we can identify that the message has already been send via the async producer
            $message = $message->withAddedMetadata('handled-async', true);

            // update ActionEvent
            $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE, $message);
            $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $this->asyncMessageProducer);

            return;
        }

        // pass ActionEvent to decorated router
        return $this->router->onRouteMessage($actionEvent);
    }
}
