<?php

declare(strict_types=1);

namespace Crell\QueueTest;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

use Doctrine\DBAL\Connection as DBALConnection;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class MessengerTest extends TestCase
{

    use DoctrineConnection;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase('test');
    }

    /**
     * @test
     */
    public function basic(): void
    {
        $logger = new MockLogger();
        $handler = new TestHandler($logger);

        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                TestMessage::class => [$handler],
            ])),
        ]);

        $bus->dispatch(new TestMessage('Hello World'));
        self::assertEquals('The message was: Hello World', $logger->messages['debug'][0]['message']);
    }

    /**
     * @test-disabled
     */
    public function with_send(): void
    {
        $logger = new MockLogger();
        $handler = new TestHandler($logger);

        $dbConn = $this->getConnection();

        $msgConn = new Connection([
            'table' => 'queue',
            'queue_name' => 'test',
            ], $dbConn);

        $doctrineSender = new DoctrineSender($msgConn);

        $container = new MockContainer();
        $container->addService('mysender', $doctrineSender);

        $phpSerializer = new PhpSerializer();

        $doctrineTransport =  new DoctrineTransport(
            new Connection([
                'table_name' => 'messages',
                'queue_name' => 'default',
                'auto_setup' => true,
            ], DriverManager::getConnection([
                'url' => 'sqlite:///db.sqlite',
            ])),
            $phpSerializer,
        );

        $doctrineFailedTransport = new DoctrineTransport(
            new Connection([
                'table_name' => 'messages',
                'queue_name' => 'failed',
                'auto_setup' => true,
            ], DriverManager::getConnection([
                'url' => 'sqlite:///db.sqlite',
            ])),
            $phpSerializer,
        );

        $container->addService('doctrine.transport', $doctrineTransport);
        $container->addService('doctrine.transport.failed', $doctrineFailedTransport);

        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator(
                [
                    TestMessage::class => ['doctrine.transport'],
                ],
                $container)),
            new HandleMessageMiddleware(new HandlersLocator([
                TestMessage::class => [$handler],
            ])),
        ]);

        $bus->dispatch(new TestMessage('Hello World'));

        $result = $dbConn->executeQuery("SELECT * FROM queue");
        $data = $result->fetchAssociative();
        var_dump($data);

        // It should not have made it through to the handler, which runs immediately.
        self::assertArrayNotHasKey('debug', $logger->messages);
    }
}

class TestMessage
{
    public function __construct(
        public readonly string $message,
    ) {}
}

class TestSender implements SenderInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(Envelope $envelope): Envelope
    {


        $this->logger->debug('The sender sent: ' . $envelope->getMessage()->message);
    }

}

class TestHandler
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function __invoke(TestMessage $message)
    {
        $this->logger->debug('The message was: ' . $message->message);
    }
}
