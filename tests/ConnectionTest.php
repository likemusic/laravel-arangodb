<?php

use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Fluent as IlluminateFluent;
use Illuminate\Support\Testing\Fakes\EventFake;
use LaravelFreelancerNL\Aranguent\Tests\TestCase;

class ConnectionTest extends TestCase
{

    /**
     * test connection
     * @test
     */
    function connection()
    {
        $this->assertInstanceOf('LaravelFreelancerNL\Aranguent\Connection', $this->connection);
    }

    /**
     * begin transaction
     * @test
     */
    function begin_transaction()
    {

        $this->assertEquals(0, $this->connection->transactionLevel());
        $this->assertEmpty($this->connection->getTransactionCommands());

        $this->connection->beginTransaction();

        $this->assertEquals(1, $this->connection->transactionLevel());
        $this->assertArrayHasKey(1, $this->connection->getTransactionCommands());
        $this->assertEmpty($this->connection->getTransactionCommands()[1]);
    }

    /**
     * add a command to the transaction
     * @test
     */
    function add_transaction_command()
    {
        $command = new IlluminateFluent([
            'name' => 'testCommandQuery',
            'command' => "db._query('INSERT {\"name\": \"Robert\", \"surname\": \"Baratheon\", \"alive\": @value, \"traits\": [\"A\",\"H\",\"C\"] } INTO migrations', {'value' : true});",
            'collections' => ['write' => 'migrations']
        ]);

        $this->connection->beginTransaction();

        $this->connection->addTransactionCommand($command);

        $commands = collect($this->connection->getTransactionCommands()[$this->connection->transactionLevel()]);
        $command = $commands->first();

        $this->assertEquals(1, $commands->count());
        $this->assertInstanceOf(IlluminateFluent::class, $command);
        $this->assertNotEmpty($command->name);
        $this->assertNotEmpty($command->command);
    }

    /**
     * compile action
     * @test
     */
    function compile_action()
    {
        $command = new IlluminateFluent([
            'name' => 'testCommandQuery',
            'command' => "db._query('INSERT {\"name\": \"Robert\", \"surname\": \"Baratheon\", \"alive\": false, \"traits\": [\"A\",\"H\",\"C\"] } INTO Characters', {'value' : 1});",
            'collections' => ['write' => 'Characters']
        ]);
        $this->connection->beginTransaction();
        $action = $this->connection->compileTransactionAction();

        $this->assertEquals("function () { var db = require('@arangodb').db;  }", $action);

        $this->connection->addTransactionCommand($command);
        $action = $this->connection->compileTransactionAction();

        $this->assertEquals("function () { var db = require('@arangodb').db; db._query('INSERT {\"name\": \"Robert\", \"surname\": \"Baratheon\", \"alive\": false, \"traits\": [\"A\",\"H\",\"C\"] } INTO Characters', {'value' : 1}); }", $action);
    }

    /**
     * commit fails if committed too soon
     * @test
     */
    function commit_fails_if_committed_too_soon()
    {
        $this->expectExceptionMessage("Transaction committed before starting one.");
        $this->connection->commit();

        $this->connection->beginTransaction();

        $this->expectExceptionMessage("Cannot commit an empty transaction.");
        $this->connection->commit();

    }

    /**
     * commit a transaction
     * @test
     */
    function commit_a_transaction()
    {
        $command1 = new IlluminateFluent([
            'name' => 'testCommandQuery',
            'command' => "db._query('INSERT {\"name\": \"Robert\", \"surname\": \"Baratheon\", \"alive\": @value, \"traits\": [\"A\",\"H\",\"C\"] } INTO migrations', {'value' : true});",
            'collections' => ['write' => 'migrations']
        ]);
        $command2 = new IlluminateFluent([
            'name' => 'testCommandQuery',
            'command' => "db._query('FOR c IN migrations RETURN c');",
            'collections' => ['read' => 'migrations']
        ]);

        $this->connection->beginTransaction();

        $this->connection->addTransactionCommand($command1);
        $this->connection->addTransactionCommand($command2);

        $results = $this->connection->commit();

        $this->assertTrue($results);
    }
}