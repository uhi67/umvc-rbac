<?php
/** @noinspection PhpUnused */

namespace uhi67\rbac\migrations;

use Exception;
use uhi67\rbac\Rbac;
use uhi67\umvc\Migration;

/**
 * This migration runs first.
 * Checks if the database is already initialised and creates tables if not (The original structure).
 */
class m230202_200000_rbac extends Migration
{
    /**
     * @return bool
     * @throws Exception
     */
    public function up()
    {
        // Check configuration and existing table
        $assignmentTable = Rbac::assignmentTableName();
        if ($this->connection->tableExists($assignmentTable)) {
            return true;
        }

        // Note: no foreign keys are defined, because the actual application database structure is not known
        $this->connection->pdo->exec(
            'CREATE TABLE "' . $assignmentTable . '" ( 
            "user_id" varchar(128),
            "item_name" varchar(50), 
            "created_at" datetime default CURRENT_TIMESTAMP,
            "created_by" varchar(128),
            PRIMARY KEY ("user_id", "item_name")
        );'
        );
        return true;
    }
}
