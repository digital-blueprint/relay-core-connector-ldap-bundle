<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreConnectorLdapBundle\Event\UserDataLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserDataLoadedTestEventSubcriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserDataLoadedEvent::class => 'onUserDataLoaded',
        ];
    }

    public function onUserDataLoaded(UserDataLoadedEvent $event)
    {
        $userData = $event->getUserData();

        $userAttributes = [];
        $userAttributes[UserAttributeProviderTest::MISC_ATTRIBUTE] = count($userData[UserAttributeProviderTest::LDAP_ROLES_ATTRIBUTE_NAME]);
        $event->setUserAttributes($userAttributes);
    }
}
