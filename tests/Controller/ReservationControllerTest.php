<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReservationControllerTest extends WebTestCase
{
    public function testReservationRejectsInvalidJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/flash-sales/999999/reserve',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{'
        );

        self::assertResponseStatusCodeSame(400);

        $responseData = json_decode(
            $client->getResponse()->getContent(),
            true
        );

        self::assertSame(
            'Request body must contain valid JSON.',
            $responseData['error'] ?? null
        );
    }
}