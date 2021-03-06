<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AdminBundle\Tests\Functional\Controller;

use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AdminControllerTest extends SuluTestCase
{
    /**
     * @var KernelBrowser
     */
    private $client;

    public function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient();
        $this->purgeDatabase();
    }

    public function testGetConfig()
    {
        $this->initPhpcr();
        $collectionType = new LoadCollectionTypes();
        $collectionType->load($this->getEntityManager());

        $this->client->jsonRequest('GET', '/admin/config');

        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $response = \json_decode($this->client->getResponse()->getContent());

        $this->assertObjectHasAttribute('sulu_admin', $response);
        $this->assertObjectHasAttribute('navigation', $response->sulu_admin);
        $this->assertObjectHasAttribute('resources', $response->sulu_admin);
        $this->assertObjectHasAttribute('routes', $response->sulu_admin);
        $this->assertObjectHasAttribute('fieldTypeOptions', $response->sulu_admin);
        $this->assertIsArray($response->sulu_admin->navigation);
        $this->assertIsArray($response->sulu_admin->routes);
        $this->assertIsObject($response->sulu_admin->resources);
        $this->assertObjectHasAttribute('sulu_preview', $response);

        $this->assertEquals('en', $response->sulu_admin->localizations[0]->localization);
        $this->assertEquals('en_us', $response->sulu_admin->localizations[1]->localization);
        $this->assertEquals('de', $response->sulu_admin->localizations[2]->localization);
        $this->assertEquals('de_at', $response->sulu_admin->localizations[3]->localization);
    }

    public function testGetNotExistingMetdata()
    {
        $this->client->jsonRequest('GET', '/admin/metadata/test1/test');

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }
}
