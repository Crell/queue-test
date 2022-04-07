<?php

declare(strict_types=1);

namespace Crell\QueueTest;

use Enqueue\Dbal\DbalConnectionFactory;
use Enqueue\Dbal\ManagerRegistryConnectionFactory;
use Enqueue\Null\NullConnectionFactory;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use PHPUnit\Framework\TestCase;
//use Doctrine\Persistence\ManagerRegistry;

class EnqueueTest extends TestCase
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
    public function makes_own_connection(): void
    {
        $factory = new DbalConnectionFactory('mysql://root:test@db:3306/test');

        $context = $factory->createContext();

        $context->createDataBaseTable();

        $destination = $context->createQueue('foo');

        $message = $context->createMessage('Hello world!');

        $context->createProducer()->send($destination, $message);

        $dbConn = $this->getConnection();

//        $data = $dbConn->executeQuery("SELECT * FROM enqueue")->fetchAllAssociative();
//        var_dump($data);

        // But there should now a record in the queue table.
        $count = $dbConn->executeQuery("SELECT COUNT(*) FROM enqueue")->fetchOne();
        self::assertEquals(1, $count);
        $record = $dbConn->executeQuery("SELECT * FROM enqueue")->fetchAssociative();
        self::assertEquals('foo', $record['queue']);
        self::assertEquals('Hello world!', $record['body']);

        // Consuming.

        $fooQueue = $context->createQueue('foo');
        $consumer = $context->createConsumer($fooQueue);

        $message = $consumer->receive();

        self::assertNotNull($message);

        self::assertEquals('Hello world!', $message->getBody());

        // This is needed to say the task is handed, or explicitly rejected.
        $consumer->acknowledge($message);
        //$consumer->reject($message);
    }

    /**
     * @test
     */
    public function makes_own_connection_with_runner(): void
    {
        $factory = new DbalConnectionFactory('mysql://root:test@db:3306/test');

        $context = $factory->createContext();

        $context->createDataBaseTable();

        $destination = $context->createQueue('foo');

        $message = $context->createMessage('Hello world!');

        $context->createProducer()->send($destination, $message);

        $dbConn = $this->getConnection();

//        $data = $dbConn->executeQuery("SELECT * FROM enqueue")->fetchAllAssociative();
//        var_dump($data);

        // But there should now a record in the queue table.
        $count = $dbConn->executeQuery("SELECT COUNT(*) FROM enqueue")->fetchOne();
        self::assertEquals(1, $count);
        $record = $dbConn->executeQuery("SELECT * FROM enqueue")->fetchAssociative();
        self::assertEquals('foo', $record['queue']);
        self::assertEquals('Hello world!', $record['body']);

        // Consuming.

        $fooConsumer = $context->createConsumer($destination);

        $subscriptionConsumer = $context->createSubscriptionConsumer();
        $subscriptionConsumer->subscribe($fooConsumer, function(Message $message, Consumer $consumer) {
            self::assertEquals('Hello world!', $message->getBody());

            $consumer->acknowledge($message);

            return true;
        });

        $subscriptionConsumer->consume(1000); // 1 sec
    }

    /**
     * @test-disabled
     */
    public function reuse_connection(): void
    {
        $connectionFactory = new NullConnectionFactory();

        // This is an interface, but there are no docs on how to use it.
        //$registry = new ManagerRegistry();

        $factory = new ManagerRegistryConnectionFactory($registry, [
            'connection_name' => 'default',
        ]);

        /** @var ConnectionFactory $connectionFactory **/
        $context = $connectionFactory->createContext();

        $destination = $context->createQueue('foo');

        $message = $context->createMessage('Hello world!');

        $context->createProducer()->send($destination, $message);


    }
}
