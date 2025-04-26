<?php /** @noinspection PhpUnused */

namespace uhi67\rbac;

use Exception;
use ReflectionException;
use Throwable;
use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use app\models\User;
use uhi67\umvc\CliHelper;
use uhi67\umvc\Command;
use uhi67\umvc\Migration;

class RbacController extends Command
{
    public function actionDefault(): int
    {
        echo "The `rbac` command manages the rbac configuration.", PHP_EOL;
        echo "Usage:", PHP_EOL, PHP_EOL;
        echo "   php app app/rbac/rbac/init -- Initializes rbac system", PHP_EOL;
        echo "   php app app/rbac/rbac/assign uid role -- Assign a role to the user.", PHP_EOL;
        echo "   php app app/rbac/rbac/revoke uid role -- Revoke a role from the user.", PHP_EOL;
        echo "   php app app/rbac/rbac/empty -- Deletes all assignments.", PHP_EOL;
        return App::EXIT_STATUS_OK;
    }

    /**
     * @throws Exception
     */
    public function actionInit(): int
    {
        $migrationName = 'm230202_200000_rbac';
        $migrationFile = __DIR__ . '/m230202_200000_rbac.php';
        /** @var Migration $className */
        $className = '\app\rbac\\' . $migrationName;
        try {
            require $migrationFile;
            $migration = new $className(['app' => $this->app, 'connection' => $this->app->connection, 'verbose' => 3]);
            echo "Applying migration to create RBAC tables", PHP_EOL;
            $success = $migration->up();
        } catch (Throwable $e) {
            AppHelper::showException($e);
            $success = false;
        }

        if (!$success) {
            echo "Migration failed", PHP_EOL;
        }

        // Check tables
        $assignmentTable = Rbac::assignmentTableName();
        if (!$this->app->connection->tableExists($assignmentTable)) {
            echo 'Error: Assignment table still does not exist';
            return App::EXIT_STATUS_ERROR;
        }
        return App::EXIT_STATUS_OK;
    }

    /**
     * @throws Exception
     */
    public function actionEmpty(): int
    {
        if (!CliHelper::confirm(
            'This will delete all existing role and permission assignments. Are you sure to proceed?'
        )) {
            echo "Cancelled.", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }
        $assignmentTable = Rbac::assignmentTableName();
        if ($this->app->connection->tableExists($assignmentTable)) {
            echo "Deleting existing assignments...", PHP_EOL;
            Assignment::deleteAll(null, [], null, $q);
            echo $q->affected . ' rows has been deleted', PHP_EOL;
            return App::EXIT_STATUS_OK;
        } else {
            echo 'Assignment table does not exist. Please run rbac/init first.';
            return App::EXIT_STATUS_ERROR;
        }
    }

    /**
     * Grants a role (permission) for a user
     *
     * Parameters: role-name, uid
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function actionAssign(): int
    {
        $params = $this->query;
        $roleName = array_shift($params);
        if (!App::$app->hasComponent('accessControl') || !App::$app->accessControl instanceof Rbac) {
            throw new Exception('No Rbac configured as accessControl component.');
        }
        $role = App::$app->accessControl->getItem($roleName);
        if (!$role) {
            echo "Unknown role '$roleName'", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }
        $uid = array_shift($params);
        $user = User::getOne(['uid' => $uid]);
        if (!$user) {
            echo "User '$uid' not found", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }

        if (!App::$app->accessControl->assign($uid, $roleName)) {
            echo "Failed to assign role '$roleName' to user '$uid'", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }
        echo "User role assigned successfully.", PHP_EOL;
        return App::EXIT_STATUS_OK;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function actionRevoke(): int
    {
        $params = $this->query;
        $roleName = array_shift($params);
        if (!App::$app->hasComponent('accessControl') || !App::$app->accessControl instanceof Rbac) {
            throw new Exception('No Rbac configured as accessControl component.');
        }
        $role = App::$app->accessControl->getItem($roleName);
        if (!$role) {
            echo "Unknown role '$roleName'", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }
        $uid = array_shift($params);
        $user = User::getOne(['uid' => $uid]);
        if (!$user) {
            echo "User '$uid' not found", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }

        if (!App::$app->accessControl->revoke($uid, $roleName)) {
            echo "Failed to revoke role '$roleName' from user '$uid'", PHP_EOL;
            return App::EXIT_STATUS_ERROR;
        }
        echo "User role revoked successfully.", PHP_EOL;
        return App::EXIT_STATUS_OK;
    }
}
