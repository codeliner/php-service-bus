<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 16.09.14 - 21:30
 */

namespace Prooph\ServiceBus\Process;

use Prooph\Common\Messaging\HasMessageName;
use Prooph\Common\Messaging\MessageHeader;
use Prooph\Common\Messaging\RemoteMessage;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Exception\RuntimeException;

/**
 * Class EventDispatch
 *
 * @package Prooph\ServiceBus\Process
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
class EventDispatch extends MessageDispatch
{
    const LOCATE_LISTENER     = "locate-listener";
    const INVOKE_LISTENER     = "invoke-listener";

    /**
     * @param mixed $event
     * @param EventBus $eventBus
     * @throws \InvalidArgumentException
     * @return EventDispatch
     */
    public static function initializeWith($event, EventBus $eventBus)
    {
        $instance = new static(static::INITIALIZE, $eventBus, array('message' => $event));

        if ($event instanceof HasMessageName) {
            $instance->setMessageName($event->messageName());
        }

        if ($event instanceof RemoteMessage) {
            if ($event->header()->type() !== MessageHeader::TYPE_EVENT) {
                throw new \InvalidArgumentException(
                    sprintf("Message %s cannot be handled. Message is not of type event.", $event->name())
                );
            }

            $instance->setMessageName($event->name());
        }

        return $instance;
    }

    /**
     * @return string|null
     */
    public function getEventName()
    {
        return $this->getMessageName();
    }

    /**
     * @param string $eventName
     * @throws \InvalidArgumentException
     */
    public function setEventName($eventName)
    {
        $this->setMessageName($eventName);
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->getMessage();
    }

    /**
     * @param mixed $event
     */
    public function setEvent($event)
    {
        $this->setMessage($event);
    }

    /**
     * @return \ArrayObject(index => callable|string|object)
     */
    public function getEventListeners()
    {
        //We cannot work with a simple default here, cause we need the exact reference to the listeners stack
        $eventListeners = $this->getParam('event-listeners');

        if (is_null($eventListeners)) {
            $eventListeners = new \ArrayObject();

            $this->setParam('event-listeners', $eventListeners);
        }

        return $eventListeners;
    }

    /**
     * @param array(index => callable|string|object) $eventHandlerCollection
     * @throws \Prooph\ServiceBus\Exception\RuntimeException
     */
    public function setEventListeners(array $eventHandlerCollection)
    {
        if ($this->getName() === self::LOCATE_LISTENER || $this->getName() === self::INVOKE_LISTENER) {
            throw new RuntimeException(
                "Cannot set event listeners. EventDispatch is already in dispatching phase."
            );
        }

        $this->setParam('event-listeners', new \ArrayObject());

        foreach ($eventHandlerCollection as $eventHandler) {
            $this->addEventListener($eventHandler);
        }
    }

    /**
     * @param callable|string|object $eventListener
     * @throws \Prooph\ServiceBus\Exception\RuntimeException
     * @throws \InvalidArgumentException
     */
    public function addEventListener($eventListener)
    {
        if ($this->getName() === self::LOCATE_LISTENER || $this->getName() === self::INVOKE_LISTENER) {
            throw new RuntimeException(
                "Cannot set event listeners. EventDispatch is already in dispatching phase."
            );
        }

        if (! is_string($eventListener) && ! is_object($eventListener) && ! is_callable($eventListener)) {
            throw new \InvalidArgumentException(sprintf(
                "Invalid event listener provided. Expected type is string, object or callable but type of %s given.",
                gettype($eventListener)
            ));
        }

        $this->getEventListeners()[] = $eventListener;
    }

    /**
     * @param callable|string|object $eventListener
     * @throws \InvalidArgumentException
     */
    public function setCurrentEventListener($eventListener)
    {
        if (! is_string($eventListener) && ! is_object($eventListener) && ! is_callable($eventListener)) {
            throw new \InvalidArgumentException(sprintf(
                "Invalid event listener provided. Expected type is string, object or callable but type of %s given.",
                gettype($eventListener)
            ));
        }

        $this->setParam('current-event-listener', $eventListener);
    }

    /**
     * @return callable|string|object|null
     */
    public function getCurrentEventListener()
    {
        return $this->getParam('current-event-listener');
    }
}
 