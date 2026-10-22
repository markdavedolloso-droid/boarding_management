<?php
class Transaction
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function run(callable $operation): mixed
    {
        try {
            $this->connection->beginTransaction();
            $result = $operation($this->connection);
            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $e;
        }
    }
}
